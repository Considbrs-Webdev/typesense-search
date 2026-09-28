<?php

namespace TypesenseSearch\Helper;

/**
 * Turns WordPress text into the plain text Typesense should store.
 *
 * WordPress keeps entities in titles and rendered content (e.g. wptexturize
 * turns "-" into "&#8211;"). Indexed as-is, Typesense tokenizes the entity,
 * snippets can cut it in half and the frontend has to decode it. Decode once
 * here, after tags are stripped, and let the frontend escape on output.
 */
class PlainText
{
    public static function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
