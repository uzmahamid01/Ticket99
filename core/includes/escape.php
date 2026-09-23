<?php

/**
 * Shared output-escaping helpers.
 *
 * Ticket99 renders user-generated values — ticket subjects, reply bodies,
 * nicknames, addresses, department names — directly into HTML from the domain
 * classes and the page scripts. These helpers give every one of those render
 * sites a single encoding routine so escaping cannot drift between them.
 *
 * Encoding happens at the point of output only. Stored values are never
 * modified, so the database keeps the text the user actually typed and the same
 * row can be re-rendered into a non-HTML context later without double-encoding.
 */

/**
 * Encode a value for interpolation into HTML text or an attribute.
 *
 * ENT_QUOTES encodes both double and single quotes, which matters because the
 * legacy templates build attributes with single quotes in several places. Null
 * is coerced to an empty string so callers can pass a missing database column
 * without emitting the deprecation notice PHP 8.1+ raises for null arguments.
 */
function e($value) {

    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Encode a multiline value and then render its newlines as line breaks.
 *
 * Order matters: the value is escaped first, so any markup the user typed is
 * inert before nl2br introduces the only tags in the result. Escaping after
 * nl2br would encode those <br /> tags into visible text instead.
 */
function eNl2br($value) {

    return nl2br(e($value));
}
