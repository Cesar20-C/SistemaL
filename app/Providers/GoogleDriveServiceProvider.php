<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;

class GoogleDriveServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        Storage::extend('google', function ($app, $config) {

            // ==== CONFIGURACIÓN DEL CLIENTE GOOGLE ====
            $client = new GoogleClient();
            $client->setClientId($config['clientId'] ?? env('GOOGLE_DRIVE_CLIENT_ID'));
            $client->setClientSecret($config['clientSecret'] ?? env('GOOGLE_DRIVE_CLIENT_SECRET'));
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            $client->setScopes([GoogleDrive::DRIVE]);

            // ==== TOKEN DE REFRESCO ====
            $refreshToken = $config['refreshToken'] ?? env('GOOGLE_DRIVE_REFRESH_TOKEN');
            if (empty($refreshToken)) {
                throw new \RuntimeException('Falta GOOGLE_DRIVE_REFRESH_TOKEN en .env');
            }

            // Obtiene y aplica un access token válido
            $client->fetchAccessTokenWithRefreshToken($refreshToken);

            // ==== SERVICIO GOOGLE DRIVE ====
            $service = new GoogleDrive($client);
            $adapter = new GoogleDriveAdapter(
                $service,
                $config['folderId'] ?? env('GOOGLE_DRIVE_FOLDER_ID')
            );

            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}
