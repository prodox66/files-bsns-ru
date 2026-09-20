<?php
// BZN-FILE-PURPOSE-20260920: src/ResourceApi.php — отдаёт список и байты ресурсов через единый межсерверный контракт.
declare(strict_types=1);

// Error: domain failures carry one safe HTTP status and never expose a physical path.
final class BznResourceApiException extends RuntimeException
{
    public function __construct(public readonly int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
}

// Value object: entrypoint emission is separated from request validation and resource storage.
final class BznResourceApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body
    ) {
    }

    // Factory: every JSON response uses one encoding and defensive header set.
    public static function json(int $status, array $payload): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new self($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], $body);
    }

    // Function: only this boundary writes status, headers and response bytes to PHP output.
    public function emit(bool $includeBody = true): never
    {
        http_response_code($this->status);
        // Cycle: normalized headers are emitted once and cannot be replaced by storage adapters.
        foreach ($this->headers as $name => $value) header((string) $name . ': ' . (string) $value);
        if ($includeBody) echo $this->body;
        exit;
    }
}

// Value object: validated logical request data contains no filesystem path.
final class BznResourceRequest
{
    private function __construct(
        public readonly string $action,
        public readonly string $resourceType,
        public readonly string $scope,
        public readonly string $owner,
        public readonly string $fileName
    ) {
    }

    // Factory: the whole external query is normalized before any resolver or catalog sees it.
    public static function fromHttp(array $query, array $config): self
    {
        $action = trim((string) ($query['action'] ?? ''));
        $resourceType = trim((string) ($query['type'] ?? ''));
        $scope = trim((string) ($query['scope'] ?? ''));
        $owner = trim((string) ($query['owner'] ?? ''));
        $fileName = trim((string) ($query['file'] ?? ''));
        $allowedActions = array_values((array) ($config['actions'] ?? []));
        $allowedScopes = array_values((array) ($config['scopes'] ?? []));
        if (!in_array($action, $allowedActions, true)) throw new BznResourceApiException(404, 'Неизвестное действие ресурса.');
        if (!isset($config['resources'][$resourceType])) throw new BznResourceApiException(404, 'Неизвестный тип ресурса.');
        if (!in_array($scope, $allowedScopes, true)) throw new BznResourceApiException(422, 'Некорректная область ресурса.');
        $userScope = (string) ($config['scopes']['user'] ?? 'user');
        // Branch: personal storage accepts only an opaque namespace, never an email or caller-supplied path.
        if ($scope === $userScope && preg_match((string) $config['ownerPattern'], $owner) !== 1) {
            throw new BznResourceApiException(422, 'Некорректный владелец ресурса.');
        }
        return new self($action, $resourceType, $scope, $owner, $fileName);
    }
}

// Service: server-to-server authentication is checked before parsing resource identity.
final class BznResourceApiAuthenticator
{
    public function __construct(private string $expectedKey)
    {
    }

    // Function: TLS transports one host-only secret; browsers never receive or submit this header.
    public function accepts(string $providedKey): bool
    {
        return $this->expectedKey !== '' && $providedKey !== '' && hash_equals($this->expectedKey, $providedKey);
    }
}

// Resolver: type, scope and opaque owner are the only inputs that may select a physical directory.
final class BznResourceDirectoryResolver
{
    public function __construct(private array $config)
    {
    }

    // Function: the returned directory is composed exclusively from trusted configuration and a validated owner ID.
    public function resolve(BznResourceRequest $request): string
    {
        $resource = (array) $this->config['resources'][$request->resourceType];
        $userScope = (string) ($this->config['scopes']['user'] ?? 'user');
        if ($request->scope !== $userScope) return (string) ($resource['demoDirectory'] ?? '');
        return rtrim((string) ($resource['userRootDirectory'] ?? ''), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $request->owner
            . DIRECTORY_SEPARATOR . trim((string) ($resource['userSubdirectory'] ?? ''), DIRECTORY_SEPARATOR);
    }
}

// Catalog: the first adapter owns PNG template discovery and byte reads without knowing HTTP or user email.
final class BznTemplateResourceCatalog
{
    public function __construct(private string $directory, private array $config, private string $scope)
    {
    }

    // Function: one strict filename rule is shared by listing and selected-file reads.
    private function validFileName(string $fileName): bool
    {
        return preg_match((string) ($this->config['filePattern'] ?? ''), $fileName) === 1
            && basename($fileName) === $fileName;
    }

    // Function: file metadata becomes one stable source-neutral descriptor.
    private function descriptor(SplFileInfo $file): array
    {
        $fileName = $file->getFilename();
        $projectSuffix = (string) ($this->config['projectSuffix'] ?? '');
        $extension = (string) ($this->config['extension'] ?? '');
        $format = (string) ($this->config['format'] ?? '');
        $writableScopes = (array) ($this->config['writableScopes'] ?? []);
        $baseName = substr($fileName, 0, -strlen($extension));
        $projectName = str_ends_with(strtolower($baseName), $projectSuffix)
            ? substr($baseName, 0, -strlen($projectSuffix))
            : $baseName;
        $modified = $file->getMTime();
        $size = $file->getSize();
        return [
            'id' => hash('sha256', $this->scope . '|' . $fileName),
            'name' => str_replace('_', ' ', $projectName),
            'file' => $fileName,
            'format' => $format,
            'revision' => hash('sha256', $fileName . '|' . $modified . '|' . $size),
            'modified' => $modified,
            'bytes' => $size,
            'scope' => $this->scope,
            'randomEnabled' => false,
            'writable' => in_array($this->scope, $writableScopes, true),
        ];
    }

    // Function: missing personal directories are valid empty galleries, while files are sorted newest first.
    public function list(): array
    {
        if (!is_dir($this->directory) || !is_readable($this->directory)) return [];
        $items = [];
        $iterator = new FilesystemIterator($this->directory, FilesystemIterator::SKIP_DOTS);
        // Cycle: directories, service files and unsupported names never enter the public contract.
        foreach ($iterator as $file) {
            if (!$file->isFile() || !$file->isReadable() || !$this->validFileName($file->getFilename())) continue;
            $items[] = $this->descriptor($file);
        }
        usort($items, static function (array $left, array $right): int {
            $modifiedOrder = ((int) $right['modified']) <=> ((int) $left['modified']);
            return $modifiedOrder !== 0 ? $modifiedOrder : strnatcasecmp((string) $left['file'], (string) $right['file']);
        });
        return $items;
    }

    // Function: selected bytes are returned only after the same filename, size and directory boundary checks.
    public function file(string $fileName): array
    {
        if (!$this->validFileName($fileName)) throw new BznResourceApiException(422, 'Некорректное имя файла ресурса.');
        $path = $this->directory . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path) || !is_readable($path)) throw new BznResourceApiException(404, 'Файл ресурса не найден.');
        $size = (int) filesize($path);
        if ($size > (int) ($this->config['maximumBytes'] ?? 0)) throw new BznResourceApiException(413, 'Файл ресурса превышает допустимый размер.');
        $body = file_get_contents($path);
        if (!is_string($body)) throw new BznResourceApiException(500, 'Файл ресурса недоступен.');
        return ['body' => $body, 'size' => $size, 'mimeType' => (string) ($this->config['mimeType'] ?? 'application/octet-stream')];
    }
}

// Facade: one input and one output hide authentication, physical directories and resource adapters.
final class BznResourceApi
{
    public function __construct(private array $config)
    {
    }

    // Function: every failure becomes a safe response that contains no host path or secret.
    public function handle(string $method, array $headers, array $query): BznResourceApiResponse
    {
        try {
            $normalizedMethod = strtoupper(trim($method));
            if (!in_array($normalizedMethod, (array) ($this->config['allowedMethods'] ?? []), true)) {
                throw new BznResourceApiException(405, 'Метод ресурса не поддерживается.');
            }
            $serviceKey = trim((string) ($this->config['serviceKey'] ?? ''));
            if ($serviceKey === '') throw new BznResourceApiException(503, 'Resource service is not configured.');
            $headerKey = (string) ($this->config['serviceKeyHeader'] ?? 'serviceKey');
            $authenticator = new BznResourceApiAuthenticator($serviceKey);
            if (!$authenticator->accepts(trim((string) ($headers[$headerKey] ?? '')))) {
                throw new BznResourceApiException(401, 'Resource service authorization required.');
            }
            $request = BznResourceRequest::fromHttp($query, $this->config);
            $resourceConfig = (array) $this->config['resources'][$request->resourceType];
            $directory = (new BznResourceDirectoryResolver($this->config))->resolve($request);
            $catalog = new BznTemplateResourceCatalog($directory, $resourceConfig, $request->scope);
            $listAction = (string) ($this->config['actions']['list'] ?? 'list');
            // Branch: list returns only descriptors; selected file bytes use their own response policy.
            if ($request->action === $listAction) {
                return BznResourceApiResponse::json(200, [
                    'ok' => true,
                    'version' => (int) ($this->config['manifestVersion'] ?? 1),
                    'resources' => $catalog->list(),
                ]);
            }
            $file = $catalog->file($request->fileName);
            $userScope = (string) ($this->config['scopes']['user'] ?? 'user');
            $cacheControl = $request->scope === $userScope
                ? (string) ($resourceConfig['userCacheControl'] ?? 'private, no-store')
                : (string) ($resourceConfig['demoCacheControl'] ?? 'public, max-age=3600');
            return new BznResourceApiResponse(200, [
                'Content-Type' => (string) $file['mimeType'],
                'Content-Length' => (string) $file['size'],
                'Cache-Control' => $cacheControl,
                'X-Content-Type-Options' => 'nosniff',
            ], (string) $file['body']);
        } catch (BznResourceApiException $error) {
            return BznResourceApiResponse::json($error->httpStatus, ['ok' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            return BznResourceApiResponse::json(500, ['ok' => false, 'error' => 'Resource service failed.']);
        }
    }
}
