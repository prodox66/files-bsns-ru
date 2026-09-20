<?php
// Contract test: logical resource requests never expose storage paths and personal owners remain opaque.
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ResourceApi.php';

const TEST_RESOURCE_API_CYCLES = 3;
const TEST_RESOURCE_SERVICE_KEY = 'resource-service-test-key';
const TEST_RESOURCE_OWNER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const TEST_OLDER_FILE = 'older_prj.png';
const TEST_NEWER_FILE = 'newer_prj.png';
const TEST_USER_FILE = 'personal_prj.png';

// Test helper: every failed resource contract stops with its own message.
function requireResourceApiCondition(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Function fixture: tests inject temporary roots while preserving production request names and policies.
function resourceApiTestConfig(string $demoDirectory, string $userRootDirectory): array
{
    return [
        'serviceKey' => TEST_RESOURCE_SERVICE_KEY,
        'serviceKeyHeader' => 'serviceKey',
        'allowedMethods' => ['GET'],
        'actions' => ['list' => 'list', 'file' => 'file'],
        'scopes' => ['demo' => 'demo', 'user' => 'user'],
        'ownerPattern' => '/^[a-f0-9]{64}$/D',
        'manifestVersion' => 1,
        'resources' => ['templates' => [
            'demoDirectory' => $demoDirectory,
            'userRootDirectory' => $userRootDirectory,
            'userSubdirectory' => 'templates',
            'projectSuffix' => '_prj',
            'extension' => '.png',
            'format' => 'png',
            'filePattern' => '/^[\p{L}\p{N}][\p{L}\p{N}._-]{0,159}_prj\.png$/iuD',
            'mimeType' => 'image/png',
            'maximumBytes' => 1024,
            'writableScopes' => ['user'],
            'demoCacheControl' => 'public, max-age=3600',
            'userCacheControl' => 'private, no-store, max-age=0',
        ]],
    ];
}

// Cycle: list order, user isolation, selected bytes and denied requests stay deterministic.
for ($cycleIndex = 0; $cycleIndex < TEST_RESOURCE_API_CYCLES; $cycleIndex++) {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bzn-resource-api-' . bin2hex(random_bytes(8));
    $demoDirectory = $root . DIRECTORY_SEPARATOR . 'templates';
    $userRoot = $root . DIRECTORY_SEPARATOR . 'users';
    $userDirectory = $userRoot . DIRECTORY_SEPARATOR . TEST_RESOURCE_OWNER . DIRECTORY_SEPARATOR . 'templates';
    mkdir($demoDirectory, 0700, true);
    mkdir($userDirectory, 0700, true);
    file_put_contents($demoDirectory . DIRECTORY_SEPARATOR . TEST_OLDER_FILE, 'older-bytes');
    file_put_contents($demoDirectory . DIRECTORY_SEPARATOR . TEST_NEWER_FILE, 'newer-bytes');
    file_put_contents($userDirectory . DIRECTORY_SEPARATOR . TEST_USER_FILE, 'personal-bytes');
    touch($demoDirectory . DIRECTORY_SEPARATOR . TEST_OLDER_FILE, 1000);
    touch($demoDirectory . DIRECTORY_SEPARATOR . TEST_NEWER_FILE, 2000);
    $api = new BznResourceApi(resourceApiTestConfig($demoDirectory, $userRoot));
    $headers = ['serviceKey' => TEST_RESOURCE_SERVICE_KEY];

    $demoResponse = $api->handle('GET', $headers, ['action' => 'list', 'type' => 'templates', 'scope' => 'demo']);
    $demoPayload = json_decode($demoResponse->body, true, 512, JSON_THROW_ON_ERROR);
    requireResourceApiCondition($demoResponse->status === 200, 'Demo list request failed.');
    requireResourceApiCondition(array_column($demoPayload['resources'], 'file') === [TEST_NEWER_FILE, TEST_OLDER_FILE], 'Demo resources are not newest first.');
    requireResourceApiCondition(!str_contains($demoResponse->body, $root), 'Demo response exposed a physical path.');

    $userResponse = $api->handle('GET', $headers, ['action' => 'list', 'type' => 'templates', 'scope' => 'user', 'owner' => TEST_RESOURCE_OWNER]);
    $userPayload = json_decode($userResponse->body, true, 512, JSON_THROW_ON_ERROR);
    requireResourceApiCondition(array_column($userPayload['resources'], 'file') === [TEST_USER_FILE], 'User list did not use the opaque owner directory.');
    requireResourceApiCondition(!str_contains($userResponse->body, TEST_RESOURCE_OWNER), 'User list exposed the physical namespace.');

    $fileResponse = $api->handle('GET', $headers, ['action' => 'file', 'type' => 'templates', 'scope' => 'user', 'owner' => TEST_RESOURCE_OWNER, 'file' => TEST_USER_FILE]);
    requireResourceApiCondition($fileResponse->status === 200 && $fileResponse->body === 'personal-bytes', 'Selected user bytes were not returned.');
    requireResourceApiCondition($fileResponse->headers['Cache-Control'] === 'private, no-store, max-age=0', 'User bytes received a public cache policy.');

    requireResourceApiCondition($api->handle('GET', [], ['action' => 'list', 'type' => 'templates', 'scope' => 'demo'])->status === 401, 'Missing service key was accepted.');
    requireResourceApiCondition($api->handle('GET', $headers, ['action' => 'list', 'type' => 'templates', 'scope' => 'user', 'owner' => 'email@example.test'])->status === 422, 'Plain email owner was accepted.');
    requireResourceApiCondition($api->handle('GET', $headers, ['action' => 'file', 'type' => 'templates', 'scope' => 'demo', 'file' => '../' . TEST_NEWER_FILE])->status === 422, 'Traversal filename was accepted.');

    unlink($demoDirectory . DIRECTORY_SEPARATOR . TEST_OLDER_FILE);
    unlink($demoDirectory . DIRECTORY_SEPARATOR . TEST_NEWER_FILE);
    unlink($userDirectory . DIRECTORY_SEPARATOR . TEST_USER_FILE);
    rmdir($userDirectory);
    rmdir(dirname($userDirectory));
    rmdir($userRoot);
    rmdir($demoDirectory);
    rmdir($root);
}

echo 'Resource API contract passed (' . TEST_RESOURCE_API_CYCLES . " cycles).\n";
