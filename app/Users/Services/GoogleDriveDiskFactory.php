<?php

namespace App\Users\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

/**
 * Builds the "google" filesystem disk configured in config/filesystems.php.
 */
class GoogleDriveDiskFactory
{
    public function make(array $config): FilesystemAdapter
    {
        $client = new GoogleClient;
        $client->setApplicationName((string) ($config['application_name'] ?? 'Sifu'));
        $client->addScope(Drive::DRIVE);

        if (! empty($config['service_account_json'])) {
            $client->setAuthConfig((string) $config['service_account_json']);
        } else {
            $client->setClientId((string) $config['client_id']);
            $client->setClientSecret((string) $config['client_secret']);
            $client->fetchAccessTokenWithRefreshToken((string) $config['refresh_token']);
        }

        $options = array_filter([
            'sharedFolderId' => $config['shared_folder_id'] ?? null,
            'teamDriveId' => $config['team_drive_id'] ?? null,
        ]);
        // Member documents contain personal data; skip the Drive trash on delete.
        $options['usePermanentDelete'] = true;

        $adapter = new GoogleDriveAdapter(new Drive($client), $config['folder'] ?? null, $options);

        return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
    }
}
