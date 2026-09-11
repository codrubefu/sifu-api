<?php

namespace Tests\Feature;

use App\Users\Exceptions\OrganizationLimitExceeded;
use App\Users\Services\OrganizationSubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class OrganizationSubscriptionMysqlTest extends TestCase
{
    public function test_mysql_migrations_and_concurrent_writes_cannot_take_the_same_last_slot(): void
    {
        $dsn = getenv('SUBSCRIPTION_TEST_MYSQL_DSN');
        if (! $dsn || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Set SUBSCRIPTION_TEST_MYSQL_DSN/USER/PASSWORD on an isolated test MySQL server; pcntl is required.');
        }
        $user = getenv('SUBSCRIPTION_TEST_MYSQL_USER');
        $password = getenv('SUBSCRIPTION_TEST_MYSQL_PASSWORD');
        $connect = fn () => new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database = 'subscription_test_'.bin2hex(random_bytes(8));
        $server = $connect();
        $server->exec("CREATE DATABASE `{$database}`");
        $default = config('database.default');
        try {
            preg_match('/host=([^;]+)/', $dsn, $host);
            preg_match('/port=([^;]+)/', $dsn, $port);
            config(['database.connections.subscription_mysql' => array_replace(config('database.connections.mysql'), [
                'url' => null, 'host' => $host[1] ?? '127.0.0.1', 'port' => $port[1] ?? '3306',
                'database' => $database, 'username' => $user, 'password' => $password,
            ]), 'database.default' => 'subscription_mysql']);
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'subscription_mysql', '--force' => true]), Artisan::output());
            $plan = DB::table('organization_plans')->where('code', 'start')->first();
            $this->assertSame(1, $plan->locations);
            $id = DB::table('organizations')->insertGetId(['name' => 'Concurrency test', 'slug' => 'concurrency', 'plan_id' => $plan->id]);
            // No inherited PDO socket may be used or closed by a forked child.
            DB::purge('subscription_mysql');
            $server = null;
            $children = [];
            for ($i = 0; $i < 2; $i++) {
                $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    fclose($pair[0]);
                    fread($pair[1], 1);
                    try {
                        app(OrganizationSubscriptionService::class)->guard([$id], function () use ($id, $i): void {
                            usleep(150000);
                            DB::table('locations')->insert(['organization_id' => $id, 'name' => 'Location '.$i]);
                        });
                        fwrite($pair[1], 'created');
                    } catch (OrganizationLimitExceeded $exception) {
                        fwrite($pair[1], $exception->details['code']);
                    } catch (\Throwable $exception) {
                        fwrite($pair[1], 'error: '.$exception::class.' '.$exception->getMessage());
                    }
                    fclose($pair[1]);
                    DB::disconnect('subscription_mysql');
                    exit(0);
                }
                fclose($pair[1]);
                $children[] = [$pid, $pair[0]];
            }
            foreach ($children as [, $socket]) { fwrite($socket, '1'); }
            $results = [];
            foreach ($children as [$pid, $socket]) {
                stream_set_timeout($socket, 15);
                $results[] = stream_get_contents($socket);
                fclose($socket);
                pcntl_waitpid($pid, $status);
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            sort($results);
            $this->assertSame(['created', 'organization_limit_exceeded'], $results);
            $this->assertSame(1, DB::table('locations')->where('organization_id', $id)->count());
        } finally {
            DB::purge('subscription_mysql');
            config(['database.default' => $default]);
            $server = $connect();
            $server->exec("DROP DATABASE `{$database}`");
        }
    }
}
