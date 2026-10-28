<?php

namespace TypesenseSearch\Frontend;

/**
 * Basic search for visitors without JavaScript.
 *
 * The Typesense search page renders results client side. A `<noscript>` form
 * submits with this query argument, and such requests are served by the regular
 * WordPress search (theme template, real search term, never cached).
 */
class NoJsFallback
{
	public const QUERY_ARG = 'ts_nojs';

	public static function isRequested(): bool
	{
		return isset($_GET[self::QUERY_ARG]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
