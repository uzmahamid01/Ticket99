<?php
date_default_timezone_set("Asia/Kolkata");
ob_start();

include 'config.php';

/**
 * Error display is opt-in.
 *
 * The legacy bootstrap forced display_errors on, which prints stack traces —
 * including connection arguments — straight into the browser. Errors are now
 * always logged and only displayed when TICKET99_DEBUG is explicitly set to 1,
 * so a served deployment fails quietly while a developer can still opt in.
 */
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', ticket99_env('TICKET99_DEBUG') === '1' ? '1' : '0');

if (!function_exists('ticket99_fail_closed')) {

/**
 * Stop the request with a deterministic, secret-safe diagnostic.
 *
 * Only the operation and a category of failure are emitted. Configuration
 * values are never included, so a missing-credential message cannot become a
 * credential-disclosure message. The same text goes to the error log with an
 * operation tag so an operator can correlate it without reading the response.
 */
function ticket99_fail_closed($operation, $detail) {

    error_log('ticket99 operation=' . $operation . ' outcome=failure detail=' . $detail);

    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
    }

    echo 'Ticket99 unavailable (' . $operation . '): ' . $detail . "\n";
    exit(1);
}

}

if (!function_exists('ticket99_require_config')) {

/**
 * Fail closed when required deploy-time configuration is absent.
 *
 * This runs before any database object is built and before the signed-in
 * account checks at the foot of this file, so a misconfigured deployment stops
 * at the boundary rather than surfacing as a confusing authentication or query
 * error further in. Only variable names are reported, never their values.
 */
function ticket99_require_config() {

    $required = array(
        'TICKET99_DB_HOST',
        'TICKET99_DB_PORT',
        'TICKET99_DB_USER',
        'TICKET99_DB_PASSWORD',
        'TICKET99_DB_NAME',
        'TICKET99_PAGE_TITLE',
        'TICKET99_WEBSITE_URL',
    );

    $missing = array();

    foreach ($required as $name) {
        if (ticket99_env($name) === null) {
            $missing[] = $name;
        }
    }

    if ($missing) {
        ticket99_fail_closed('config_bootstrap', 'missing required configuration: ' . implode(', ', $missing));
    }
}

}

ticket99_require_config();

// Identity lives in the session, so it must exist before any code that asks who
// is making this request — the account checks at the foot of this file included.
require_once 'core/security/session.php';
ticket99_session_start();

$male = array();
	$female = array();
	$m = rand(0, 2);
	$f = rand(0, 1);
    $male[] = '<img src="/helpdesk-master/images/avatar1.png">';
    $male[] = '<img src="/helpdesk-master/images/avatar2.png">';
    $male[] = '<img src="/helpdesk-master/images/avatar3.png">';

    $female[] = '<img src="/helpdesk-master/images/avatar4.png">';
    $female[] = '<img src="/helpdesk-master/images/avatar5.png">';

define('HOST', $host);
define('PORT', $port);
define('USER', $username);
define('PASSWORD', $password);
define('DB_NAME', $database_name);

require_once 'core/includes/escape.php';
require 'core/db.php';
require 'core/users.php';
require 'core/time.php';
require 'core/tickets.php';
require 'core/admin.php';

$database = new db;
$users = new users;
$time = new time;
$tickets = new tickets;
$admin = new admin;

// The signed-in account checks can redirect and die (users::is_locked), which an
// unauthenticated probe such as health.php must not trigger. Such callers define
// TICKET99_SKIP_ACCOUNT_CHECKS before including this file; ordinary page scripts
// and core/process.php define nothing and behave exactly as before.
if(!defined('TICKET99_SKIP_ACCOUNT_CHECKS') && $users->signed_in()){
	  $users->account_exists();
	  $users->is_locked();
}
