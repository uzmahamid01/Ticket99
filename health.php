<?php

/**
 * Unauthenticated runtime health probe.
 *
 * Reports component-level status for the three things a Ticket99 deployment
 * needs to serve a request: the PHP runtime, session read-write capability, and
 * the MySQL connection. It exercises the same bootstrap and the same
 * db::getDBH path the application uses, so a green probe means the real
 * persistence boundary works rather than that a parallel check passed.
 *
 * Returns 200 when every component is ok and 503 when any component fails.
 * The response carries categories only — never configuration values, the PDO
 * DSN, or exception text from the driver.
 */

// Report a failed dependency in this endpoint's own response shape instead of
// letting the persistence boundary kill the request with its own payload.
define('TICKET99_DB_THROW', true);

// This probe is unauthenticated by design; skip the signed-in account checks,
// which can redirect and die.
define('TICKET99_SKIP_ACCOUNT_CHECKS', true);

require __DIR__ . '/init.php';

$components = array();

// --- PHP runtime ---------------------------------------------------------
$components['php'] = array(
    'status'  => 'ok',
    'version' => PHP_VERSION,
);

// --- Session read/write/delete -------------------------------------------
$session_key = 'ticket99_health_check';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $probe = 'probe-' . bin2hex(random_bytes(4));

    $_SESSION[$session_key] = $probe;
    $read_back = isset($_SESSION[$session_key]) ? $_SESSION[$session_key] : null;
    unset($_SESSION[$session_key]);

    if ($read_back !== $probe) {
        $components['session'] = array('status' => 'error', 'detail' => 'session value did not read back');
    } elseif (isset($_SESSION[$session_key])) {
        $components['session'] = array('status' => 'error', 'detail' => 'session value did not clear');
    } else {
        $components['session'] = array('status' => 'ok');
    }
} catch (Throwable $e) {
    $components['session'] = array('status' => 'error', 'detail' => 'session unavailable');
}

// --- Database connectivity -----------------------------------------------
// SELECT 1 is deliberate: it proves the connection and the credentials without
// reading or writing any application table.
try {
    $handle = $database->getDBH();
    $result = $handle->query('SELECT 1');

    $components['database'] = $result
        ? array('status' => 'ok')
        : array('status' => 'error', 'detail' => 'probe query returned no result');
} catch (Throwable $e) {
    $components['database'] = array('status' => 'error', 'detail' => 'database unreachable');
}

// --- Aggregate and respond ------------------------------------------------
$healthy = true;

foreach ($components as $component) {
    if ($component['status'] !== 'ok') {
        $healthy = false;
    }
}

// init.php opens an output buffer; discard anything the bootstrap emitted so the
// response body is the health document and nothing else.
if (ob_get_level() > 0) {
    ob_clean();
}

if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    http_response_code($healthy ? 200 : 503);
}

echo json_encode(array(
    'status'     => $healthy ? 'ok' : 'unhealthy',
    'components' => $components,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
