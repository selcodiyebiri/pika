<?php
define("BASE_FOLDER", parse_url(currentSiteUrl(), PHP_URL_PATH) ?: '');

define("SITE_CACHE_PATH", BASEPATH . "/content/cache");
define("SITE_LOG_PATH", BASEPATH . "/content/logs");
define("SITE_UPLOAD_PATH", BASEPATH . "/public/uploads/");

/* Themes: content/themes/<theme>; views fall back to content/themes/shared */
define("SITE_THEME_PATH_MAIN", BASEPATH . "/content/themes");
define("SITE_SHARED_THEME_PATH", SITE_THEME_PATH_MAIN . "/shared");

/* Public asset URLs: files live in public/assets (admin assets in public/assets/admin) */
define("SITE_ASSET_URI", currentSiteUrl() . "/assets");
define("SITE_ADMIN_ASSET_URI", SITE_ASSET_URI . "/admin");
