<?php

/**
 * Untrusted-input fixtures for the escaping helpers.
 *
 * Each payload mirrors a real Ticket99 field that accepts user input and is
 * rendered back into HTML: ticket subjects and bodies, reply text, account
 * nicknames and addresses, profile URLs, and department names.
 *
 * No database connection is required — these are plain strings so the escaping
 * behaviour can be tested without a MySQL runtime.
 */

return array(

    'ticket_subject' => '<script>alert(1)</script>',

    'ticket_body' => "First line with <b>markup</b>\nSecond line with an & ampersand\nThird line",

    'reply_text' => "Quoting the customer: \"it's broken\"\n<img src=x onerror=alert(1)>",

    'nickname' => "O'Brien <admin>",

    'email' => 'user+tag@example.com',

    'profile_url' => "javascript:alert('xss')",

    'department_name' => 'Billing & Accounts <Tier 1>',

    'attribute_breakout' => "' onmouseover='alert(1)",

    'empty_string' => '',

    'null_value' => null,
);
