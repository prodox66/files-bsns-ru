# Static public routes

portfolio/app.js::PublicPortfolio::mount <- DOM deferred script [/.htaccess virtual username/profile, username/portfolio, prototype/hash]
portfolio/app.js::request <- mount [HTTP GET metadataDomain/portfolio-api/]
portfolio/app.js::image <- mount [file-service signed WebP URL; explicit open navigates WP to record a native viewer]
portfolio/app.js::link <- image,page,mount
portfolio/app.js::safeUrl <- link,image,mount
portfolio/app.js::page <- mount [previous/next link callbacks]

No PHP, WP filesystem includes, database credentials or file-service keys are present in this static site.
