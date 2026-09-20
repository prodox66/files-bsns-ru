# Resource API — граф вызовов

Целевая граница перед реализацией, 20.09.2026 МСК.

`resource-api.php::HTTP entrypoint <- design.bzn.ru same-origin gateway [server-to-server HTTP]`

`src/ResourceApi.php::BznResourceApi.handle <- resource-api.php::HTTP entrypoint, tests/Resource_Api.test.php`

`src/ResourceApi.php::BznResourceApiAuthenticator.accepts <- src/ResourceApi.php::BznResourceApi.handle`

`src/ResourceApi.php::BznResourceRequest.fromHttp <- src/ResourceApi.php::BznResourceApi.handle`

`src/ResourceApi.php::BznResourceDirectoryResolver.resolve <- src/ResourceApi.php::BznResourceApi.handle`

`src/ResourceApi.php::BznTemplateResourceCatalog.list <- src/ResourceApi.php::BznResourceApi.handle [action=list]`

`src/ResourceApi.php::BznTemplateResourceCatalog.file <- src/ResourceApi.php::BznResourceApi.handle [action=file]`

`src/ResourceApi.php::BznResourceApiResponse.emit <- resource-api.php::HTTP entrypoint`

Внешний запрос передаёт только логический `type`, `scope`, непрозрачный `owner` и действие. Физический каталог остаётся внутри resolver. Браузер и вызывающий gateway не получают filesystem path.
