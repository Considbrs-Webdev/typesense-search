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
		$hitsPerPage = (int) get_option(Settings::OPTION_HITS_PER_PAGE, 10);

		$config = [
			'host'       => esc_url_raw($host),
			'collection' => sanitize_text_field($collection),
			'searchKey'  => sanitize_text_field($searchKey),
			'hitsPerPage' => $hitsPerPage,
		];

		// Include configured facets (field, label, placeholder, display_as)
		$rawFacets = (array) get_option(\TypesenseSearch\Admin\Settings::OPTION_FACETS, []);
		$facets = [];
		foreach ($rawFacets as $f) {
			if (!is_array($f)) {
				continue;
			}
			$field = sanitize_text_field($f['field'] ?? '');
			if (empty($field)) {
				continue;
			}
			$display = sanitize_text_field($f['display_as'] ?? 'dropdown');
			if (!in_array($display, ['dropdown', 'button_group'], true)) {
				$display = 'dropdown';
			}

			$facets[] = [
				'field'       => $field,
				'label'       => sanitize_text_field($f['label'] ?? ''),
				'placeholder' => sanitize_text_field($f['placeholder'] ?? ''),
				'display_as'  => $display,
			];
		}

		if (!empty($facets)) {
			$config['facets'] = $facets;
		}

		wp_localize_script('typesense-search', 'typesenseConfig', $config);
	}
}

