<?php

/**
 * Deploy-time configuration for Ticket99.
 *
 * Every value resolves from the process environment, so no credential, database
 * name, or deployment URL is committed to source control. See .env.example for
 * the full list of variable names a deployment must provide.
 *
 * The first user to register will be given administration rights automatically.
 * The database and tables will be created automatically when the page is loaded
 * for the first time.
 */

/**
 * Read a deploy-time value from the environment.
 *
 * $_ENV and $_SERVER are checked before getenv() because the SAPIs this app runs
 * under populate them differently: Apache/mod_php exposes SetEnv values through
 * $_SERVER, while CLI and FPM commonly populate $_ENV. An empty string counts as
 * absent so a blank variable falls through to the default rather than silently
 * configuring the app with nothing.
 */
if (!function_exists('ticket99_env')) {

function ticket99_env($name, $default = null) {

    if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }

    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    $value = getenv($name);

    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

}

$host          = ticket99_env('TICKET99_DB_HOST');
$port          = ticket99_env('TICKET99_DB_PORT', '3306');
$username      = ticket99_env('TICKET99_DB_USER');
$password      = ticket99_env('TICKET99_DB_PASSWORD');
$database_name = ticket99_env('TICKET99_DB_NAME');

$page_title  = ticket99_env('TICKET99_PAGE_TITLE', 'HelpDesk'); //Page title that will appear in the browser tab.
$website_url = ticket99_env('TICKET99_WEBSITE_URL'); //Link to the website where this is hosted. Include the scheme and a trailing slash.

//For support, please email adaptcoder@gmail.com or create an issue at: https://github.com/shameemreza/helpdesk.
