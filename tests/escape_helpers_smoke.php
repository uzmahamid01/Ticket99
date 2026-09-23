<?php

/**
 * Smoke tests for the shared escaping helpers.
 *
 * Runs without MySQL: it requires core/includes/escape.php directly rather than
 * init.php, so the encoding contract can be checked with no database or
 * environment configuration present.
 *
 * Usage: php tests/escape_helpers_smoke.php
 * Exits 0 when every assertion passes, 1 otherwise.
 */

require_once __DIR__ . '/../core/includes/escape.php';

$payloads = require __DIR__ . '/fixtures/escape_payloads.php';

$failures = array();
$checks   = 0;

function check($label, $condition, &$failures, &$checks) {
    $checks++;
    if ($condition) {
        echo "  PASS  " . $label . "\n";
    } else {
        echo "  FAIL  " . $label . "\n";
        $failures[] = $label;
    }
}

echo "escape helpers smoke\n";

// --- e(): markup is neutralised -------------------------------------------
$subject = e($payloads['ticket_subject']);
check('e() encodes angle brackets', strpos($subject, '&lt;script&gt;') !== false, $failures, $checks);
check('e() leaves no raw <script', strpos($subject, '<script') === false, $failures, $checks);

// --- e(): ENT_QUOTES covers single quotes ---------------------------------
$quoted = e("<b>'x'</b>");
check('e() encodes the opening tag', strpos($quoted, '&lt;b&gt;') !== false, $failures, $checks);
check('e() encodes single quotes (ENT_QUOTES)', strpos($quoted, '&#039;') !== false, $failures, $checks);

$breakout = e($payloads['attribute_breakout']);
check('e() blocks attribute breakout', strpos($breakout, "'") === false, $failures, $checks);

// --- e(): ampersands and double quotes ------------------------------------
$department = e($payloads['department_name']);
check('e() encodes bare ampersands', strpos($department, '&amp;') !== false, $failures, $checks);

$reply = e($payloads['reply_text']);
check('e() encodes double quotes', strpos($reply, '&quot;') !== false, $failures, $checks);

// --- e(): null and empty are safe -----------------------------------------
check('e() maps null to an empty string', e($payloads['null_value']) === '', $failures, $checks);
check('e() maps empty to an empty string', e($payloads['empty_string']) === '', $failures, $checks);

// --- eNl2br(): escape first, then break -----------------------------------
$body = eNl2br($payloads['ticket_body']);
check('eNl2br() inserts break tags', strpos($body, '<br') !== false, $failures, $checks);
check('eNl2br() still encodes markup', strpos($body, '&lt;b&gt;') !== false, $failures, $checks);
check('eNl2br() leaves no raw markup', strpos($body, '<b>') === false, $failures, $checks);

$script_body = eNl2br("line one\n<script>alert(1)</script>");
check('eNl2br() neutralises script payloads', strpos($script_body, '<script') === false, $failures, $checks);
check('eNl2br() keeps the break for that payload', strpos($script_body, '<br') !== false, $failures, $checks);
check('eNl2br() does not encode its own break tags', strpos($script_body, '&lt;br') === false, $failures, $checks);

// --- stored values are never mutated --------------------------------------
$original = $payloads['ticket_subject'];
e($original);
eNl2br($original);
check('helpers do not mutate their input', $original === $payloads['ticket_subject'], $failures, $checks);

echo "\n" . ($checks - count($failures)) . "/" . $checks . " passed\n";

if ($failures) {
    echo "FAILED: " . implode('; ', $failures) . "\n";
    exit(1);
}

echo "OK\n";
exit(0);
