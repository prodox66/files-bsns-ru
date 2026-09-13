# PNG-only template manifest call graph

`templates/manifest.php::templateManifestConfig <- templates/manifest.php::<module>`

`templates/manifest.php::TemplateManifestCatalog.fileFormat <- TemplateManifestCatalog.availableFiles, TemplateManifestCatalog.derivedId`

`templates/manifest.php::TemplateManifestCatalog.manifest <- templates/manifest.php::<module> [GET/HEAD]`

Целевая граница: `allowedFormats` объявляет только `.png`; PHP, JSON и остальные файлы не входят в публичный список.
