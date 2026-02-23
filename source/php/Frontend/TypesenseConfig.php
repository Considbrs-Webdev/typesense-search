<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Admin\Settings;

/**
 * Class TypesenseConfig
 *
 * Localizes Typesense configuration for the frontend search script.
 */
class TypesenseConfig
{
	public function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'localizeConfig'], 11);
	}

	/**
	 * Localize the Typesense config to the frontend `typesense-search` script.
	 */
	public function localizeConfig(): void
	{
		if (!is_search()) {
			return;
		}

		$host       = get_option(Settings::OPTION_REMOTE, '');
		$collection = get_option(Settings::OPTION_INDEX_NAME, '');
		$searchKey  = get_option(Settings::OPTION_SEARCH_KEY, '');

		$config = [
			'host'       => esc_url_raw($host),
			'collection' => sanitize_text_field($collection),
			'searchKey'  => sanitize_text_field($searchKey),
		];

		wp_localize_script('typesense-search', 'typesenseConfig', $config);
	}
}

