# Граф вызовов каталога шаблонов

`templates/manifest.php::templateManifestConfig <- templates/manifest.php::глобальная точка входа HTTP`

`templates/manifest.php::TemplateManifestCatalog::manifest <- templates/manifest.php::глобальная точка входа HTTP`

`templates/manifest.php::TemplateManifestCatalog::metadataEntries <- templates/manifest.php::TemplateManifestCatalog::manifest`

`templates/manifest.php::TemplateManifestCatalog::availableFiles <- templates/manifest.php::TemplateManifestCatalog::manifest`

`templates/manifest.php::TemplateManifestCatalog::fileFormat <- templates/manifest.php::TemplateManifestCatalog::availableFiles`

`templates/manifest.php::TemplateManifestCatalog::templateEntry <- templates/manifest.php::TemplateManifestCatalog::manifest`

`templates/manifest.php::hash(file revision) <- templates/manifest.php::TemplateManifestCatalog::templateEntry [версия оригинала для кэша общего preview-сервиса]`

`templates/manifest.php::TemplateManifestCatalog::derivedId <- templates/manifest.php::TemplateManifestCatalog::templateEntry`

`templates/manifest.php::TemplateManifestCatalog::derivedName <- templates/manifest.php::TemplateManifestCatalog::templateEntry`

`templates/manifest.php::respondWithTemplateManifest <- templates/manifest.php::глобальная точка входа HTTP`

Внешний потребитель после подключения шлюза: `design-bzn-ru/templates/save-template.php`.

`templates/manifest.json <- design-bzn-ru/templates/save-template.php::HttpTemplateGallerySource::listTemplates [текущий production-источник, пока files.bsns.ru не исполняет PHP; каждая запись обязана иметь id и file]`
