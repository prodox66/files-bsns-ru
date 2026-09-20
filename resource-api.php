<?php
// BZN-FILE-PURPOSE-20260920: resource-api.php — принимает только авторизованные межсерверные запросы ресурсов.
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'resource-api.config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ResourceApi.php';

$config = bznResourceApiConfig();
$headerKey = (string) ($config['serviceKeyHeader'] ?? 'serviceKey');
$headers = [$headerKey => (string) ($_SERVER['HTTP_X_BZN_SERVICE_KEY'] ?? '')];
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$response = (new BznResourceApi($config))->handle($method, $headers, $_GET);
$response->emit(true);
