<?php
// BZN-FILE-PURPOSE-20260913: templates/manifest.php — формирует публичное оглавление реально существующих PNG/JSON-шаблонов.
declare(strict_types=1);

// Function: all filesystem, response and format settings are declared in one replaceable boundary.
function templateManifestConfig(): array
{
    $directoryPath = __DIR__;
    $metadataFileName = 'manifest.json';
    $manifestVersion = 1;
    $allowedMethods = ['GET', 'HEAD'];
    $allowedFormats = [
        '.bzn.json' => 'json',
        '.png' => 'png',
    ];
    $defaultRandomEnabled = false;
    $responseHeaders = [
        'Content-Type: application/json; charset=utf-8',
        'Cache-Control: no-store, max-age=0',
        'X-Content-Type-Options: nosniff',
    ];

    return [
        'directoryPath' => $directoryPath,
        'metadataPath' => $directoryPath . DIRECTORY_SEPARATOR . $metadataFileName,
        'manifestVersion' => $manifestVersion,
        'allowedMethods' => $allowedMethods,
        'allowedFormats' => $allowedFormats,
        'defaultRandomEnabled' => $defaultRandomEnabled,
        'responseHeaders' => $responseHeaders,
    ];
}

// Class: the public endpoint sees one catalog operation while scanning and metadata rules remain internal.
final class TemplateManifestCatalog
{
    public function __construct(private readonly array $config)
    {
    }

    // Function: return the current list built only from files that exist in the configured directory.
    public function manifest(): array
    {
        $metadataEntries = $this->metadataEntries();
        $availableFiles = $this->availableFiles();
        $entriesByFile = [];

        // Cycle: metadata controls labels and order but can never publish a missing file.
        foreach ($metadataEntries as $metadataEntry) {
            $fileName = (string) ($metadataEntry['file'] ?? '');
            if (!isset($availableFiles[$fileName])) {
                continue;
            }
            $entriesByFile[$fileName] = $this->templateEntry($fileName, $metadataEntry, $availableFiles[$fileName]);
            unset($availableFiles[$fileName]);
        }

        // Cycle: newly uploaded supported files appear automatically even before a label is added to metadata.
        foreach ($availableFiles as $fileName => $format) {
            $entriesByFile[$fileName] = $this->templateEntry($fileName, [], $format);
        }

        return [
            'version' => (int) $this->config['manifestVersion'],
            'templates' => array_values($entriesByFile),
        ];
    }

    // Function: optional metadata changes presentation only; a damaged file falls back to derived labels.
    private function metadataEntries(): array
    {
        $metadataPath = (string) $this->config['metadataPath'];
        if (!is_file($metadataPath) || !is_readable($metadataPath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($metadataPath), true);
        return is_array($decoded) && is_array($decoded['templates'] ?? null)
            ? $decoded['templates']
            : [];
    }

    // Function: one directory scan recognizes only configured project formats and ignores service files.
    private function availableFiles(): array
    {
        $files = [];
        $directory = new FilesystemIterator((string) $this->config['directoryPath'], FilesystemIterator::SKIP_DOTS);

        // Cycle: directories, PHP, metadata and unknown extensions never enter the public manifest.
        foreach ($directory as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $fileName = $fileInfo->getFilename();
            $format = $this->fileFormat($fileName);
            if ($format !== null) {
                $files[$fileName] = $format;
            }
        }
        ksort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    // Function: extension matching lives in one place so a future storage format changes no caller.
    private function fileFormat(string $fileName): ?string
    {
        $normalizedFileName = strtolower($fileName);

        // Cycle: longer configured suffixes are checked before ordinary extensions.
        foreach ($this->config['allowedFormats'] as $suffix => $format) {
            if (str_ends_with($normalizedFileName, (string) $suffix)) {
                return (string) $format;
            }
        }
        return null;
    }

    // Function: metadata and derived defaults become one stable gallery entry contract.
    private function templateEntry(string $fileName, array $metadata, string $format): array
    {
        $derivedId = $this->derivedId($fileName);
        $configuredId = trim((string) ($metadata['id'] ?? ''));
        $configuredName = trim((string) ($metadata['name'] ?? ''));
        return [
            'id' => $configuredId !== '' ? $configuredId : $derivedId,
            'name' => $configuredName !== '' ? $configuredName : $this->derivedName($derivedId),
            'file' => $fileName,
            'format' => $format,
            'randomEnabled' => (bool) ($metadata['randomEnabled'] ?? $this->config['defaultRandomEnabled']),
        ];
    }

    // Function: a filename becomes a stable identifier without exposing its project extension.
    private function derivedId(string $fileName): string
    {
        $normalizedFileName = strtolower($fileName);
        foreach (array_keys($this->config['allowedFormats']) as $suffix) {
            if (str_ends_with($normalizedFileName, (string) $suffix)) {
                return substr($fileName, 0, -strlen((string) $suffix));
            }
        }
        return pathinfo($fileName, PATHINFO_FILENAME);
    }

    // Function: an uploaded filename receives a readable fallback label until metadata names it explicitly.
    private function derivedName(string $templateId): string
    {
        return strtoupper(str_replace(['-', '_'], ' ', $templateId));
    }
}

// Function: HTTP details remain outside the catalog's filesystem logic.
function respondWithTemplateManifest(int $status, array $payload, array $headers, bool $includeBody): never
{
    http_response_code($status);
    foreach ($headers as $headerValue) {
        header((string) $headerValue);
    }
    if ($includeBody) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    exit;
}

$config = templateManifestConfig();
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// Branch: the catalog is read-only and accepts only requests that cannot modify server state.
if (!in_array($requestMethod, $config['allowedMethods'], true)) {
    respondWithTemplateManifest(405, ['ok' => false, 'error' => 'Разрешено только чтение списка шаблонов.'], $config['responseHeaders'], true);
}

try {
    $catalog = new TemplateManifestCatalog($config);
    respondWithTemplateManifest(200, $catalog->manifest(), $config['responseHeaders'], $requestMethod !== 'HEAD');
} catch (Throwable $error) {
    respondWithTemplateManifest(500, ['ok' => false, 'error' => 'Не удалось сформировать список шаблонов.'], $config['responseHeaders'], $requestMethod !== 'HEAD');
}
