<?php

namespace Tests\Unit;

use App\Sms\Services\SmsPortalService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsPortalServiceTest extends TestCase
{
    public function test_it_sends_sms_through_sms_portal_with_plain_ascii_message(): void
    {
        Http::fake([
            'https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200),
        ]);

        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');

        $sent = app(SmsPortalService::class)->send('0722535723', 'Ștefan mănâncă înghețată 😊');

        $this->assertTrue($sent);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://mtws.smsportal.ro/main.aspx')
                && $request['Dest'] === '0722535723'
                && $request['Msg'] === 'Stefan mananca inghetata'
                && $request['User'] === 'acomtws'
                && $request['Pass'] === 'secret'
                && $request['Enc'] === 0
                && $request['La'] === 1733;
        });
    }

    public function test_it_reports_failed_sms_portal_requests(): void
    {
        Http::fake([
            'https://mtws.smsportal.ro/main.aspx*' => Http::response('Error', 500),
        ]);

        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');

        $this->assertFalse(app(SmsPortalService::class)->send('0722535723', 'test message'));
    }
}
