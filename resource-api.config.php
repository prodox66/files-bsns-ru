<?php
// BZN-FILE-PURPOSE-20260920: resource-api.config.php — сопоставляет логические ресурсы физическим владельцам хранилища.
declare(strict_types=1);

// Function: deployment secrets are loaded separately while repository defaults keep the transport contract portable.
function bznResourceApiConfig(): array
{
    $localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'resource-api.local.php';
    $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
    $local = is_array($localConfig) ? $localConfig : [];
    $bytesPerMegabyte = 1024 * 1024;
    $templateType = 'templates';
    $demoScope = 'demo';
    $userScope = 'user';
    $listAction = 'list';
    $fileAction = 'file';

    return [
        'serviceKey' => trim((string) ($local['service_key'] ?? '')),
        'serviceKeyHeader' => 'serviceKey',
        'allowedMethods' => ['GET'],
        'actions' => ['list' => $listAction, 'file' => $fileAction],
        'scopes' => ['demo' => $demoScope, 'user' => $userScope],
        'ownerPattern' => '/^[a-f0-9]{64}$/D',
        'manifestVersion' => 1,
        'resources' => [
            $templateType => [
                'demoDirectory' => __DIR__ . DIRECTORY_SEPARATOR . 'templates',
                'userRootDirectory' => __DIR__ . DIRECTORY_SEPARATOR . 'users',
                'userSubdirectory' => 'templates',
                'projectSuffix' => '_prj',
                'extension' => '.png',
                'format' => 'png',
                'filePattern' => '/^[\p{L}\p{N}][\p{L}\p{N}._-]{0,159}_prj\.png$/iuD',
                'mimeType' => 'image/png',
                'maximumBytes' => 64 * $bytesPerMegabyte,
                'writableScopes' => [$userScope],
                'demoCacheControl' => 'public, max-age=3600',
                'userCacheControl' => 'private, no-store, max-age=0',
            ],
        ],
    ];
}
