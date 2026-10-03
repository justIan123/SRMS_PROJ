<?php
define("DS", DIRECTORY_SEPARATOR);

// root of the project
define("SITE_ROOT", dirname(__FILE__, 2));

//constants for file sys
define("APP_PATH", SITE_ROOT . DS . "app");
define("COMPONENTS_PATH", SITE_ROOT . DS . "components");
define("CONFIG_PATH", SITE_ROOT . DS . "config");
define("DEV_PATH", SITE_ROOT . DS . "dev");
define("DEBUG_PATH", SITE_ROOT . DS . "dev" . DS . "debug" . DS);
define("LOGS_PATH", SITE_ROOT . DS . "dev" . DS . "logs" . DS);
define("FUNCTIONS_PATH", SITE_ROOT . DS . "functions");
define("HANDLERS_PATH", SITE_ROOT . DS . "handlers");
define("INCLUDE_PATH", SITE_ROOT . DS . "include");
define("PUBLIC_PATH", SITE_ROOT . DS . "public");
define("IMAGES_PATH", SITE_ROOT . DS . "images");

// USAGE
// require_once(INCLUDE_PATH . DS . "page.php");

// urlk paths (for browser links/forms)
define("BASE_URL", "http://localhost/1_SRMS/");
define("HANDLERS_URL", BASE_URL . "handlers/");
define("APP_URL", BASE_URL . "app/");
// require_once(HANDLERS_URL . "pageProcess.php");

?>
