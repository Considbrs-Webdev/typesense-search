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
		$isSearch          = is_search();
		$quickSearchEnabled = (bool) get_option(Settings::OPTION_QUICK_SEARCH_ENABLED, 0);

		if (!$isSearch && !$quickSearchEnabled) {
			return;
		}

		$host         = get_option(Settings::OPTION_REMOTE, '');
		$frontendHost = get_option(Settings::OPTION_FRONTEND_HOST, '');
		$collection   = get_option(Settings::OPTION_INDEX_NAME, '');
		$searchKey    = get_option(Settings::OPTION_SEARCH_KEY, '');
		$hitsPerPage              = (int) get_option(Settings::OPTION_HITS_PER_PAGE, 10);
		$debounce                 = (bool) get_option(Settings::OPTION_DEBOUNCE, true);
		$debounceDelay            = (int) get_option(Settings::OPTION_DEBOUNCE_DELAY, 300);
		$highlightAffixNumTokens  = (int) get_option(Settings::OPTION_HIGHLIGHT_AFFIX_NUM_TOKENS, 15);

		$config = [
			'host'                    => esc_url_raw($frontendHost ?: $host),
			'collection'              => sanitize_text_field($collection),
			'searchKey'               => sanitize_text_field($searchKey),
			'hitsPerPage'             => $hitsPerPage,
			'debounce'                => $debounce,
			'debounceDelay'           => $debounceDelay,
			'highlightAffixNumTokens' => $highlightAffixNumTokens,
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

		/**
		 * Filters the post_type → template_key mapping for hit template selection.
		 *
		 * @param array<string, string> $mapping Map of post_type => template_key.
		 */
		$templateMapping = (array) apply_filters('Municipio/TypesenseSearch/postTypeToTemplate', []);
		$config['templateMapping'] = array_map('sanitize_text_field', $templateMapping);

		/**
		 * Filters the placeholder key → document field path mapping for dynamic placeholders.
		 *
		 * @param array<string, string> $mappings Map of placeholder_key => field_path (e.g. SEARCH_HIT_DATE_OVERLAY => date_overlay).
		 */
		$placeholderMappings = (array) apply_filters('Municipio/TypesenseSearch/placeholderMappings', []);
		$config['placeholderMappings'] = array_map('sanitize_text_field', $placeholderMappings);

		if ($isSearch) {
			wp_localize_script('typesense-search', 'typesenseConfig', $config);
		}

		if ($quickSearchEnabled) {
			// quickSearchHitsPerPage defaults to 5; can be made a setting in the future.
			$config['quickSearchHitsPerPage'] = 5;

			wp_localize_script('typesense-quick-search', 'typesenseConfig', $config);

			// Build selectors list from saved option
			$rawSelectors = (array) get_option(Settings::OPTION_QUICK_SEARCH_SELECTORS, []);
			$selectors    = array_values(array_filter(array_map(
				fn($s) => is_array($s) ? sanitize_text_field($s['selector'] ?? '') : '',
				$rawSelectors,
			)));

			wp_localize_script('typesense-quick-search', 'typesenseQuickSearchConfig', [
				'selectors' => $selectors,
			]);
		}
	}
}

