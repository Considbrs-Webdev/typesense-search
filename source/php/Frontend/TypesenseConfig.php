<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Helper\CacheBust;
use TypesenseSearch\Services\SettingsRepository;

/**
 * Class TypesenseConfig
 *
 * Localizes Typesense configuration for the frontend search script.
 */
class TypesenseConfig
{
	private SettingsRepository $settings;

	/**
	 * Map WordPress locale to a Web Awesome translation module (dist/translations/{key}.js).
	 * Null means use built-in English strings for component internals.
	 *
	 * @return string|null
	 */
	private static function webAwesomeLocale(): ?string
	{
		$locale = get_locale();
		$exact  = [
			'sv_SE' => 'sv',
			'en_GB' => 'en-gb',
			'nb_NO' => 'nb',
			'nn_NO' => 'nn',
			'da_DK' => 'da',
			'fi'    => 'fi',
		];
		if (isset($exact[$locale])) {
			return $exact[$locale];
		}
		$lang = strtok($locale, '_') ?: $locale;
		$byLanguage = [
			'sv' => 'sv',
			'da' => 'da',
			'nb' => 'nb',
			'nn' => 'nn',
			'fi' => 'fi',
			'de' => 'de',
			'fr' => 'fr',
			'es' => 'es',
			'nl' => 'nl',
			'pl' => 'pl',
			'pt' => 'pt',
			'it' => 'it',
		];
		return $byLanguage[$lang] ?? null;
	}

	public function __construct(SettingsRepository $settings)
	{
		$this->settings = $settings;
		add_action('wp_enqueue_scripts', [$this, 'localizeConfig'], 11);
	}

	/**
	 * Localize the Typesense config to the frontend `typesense-search` script.
	 */
	public function localizeConfig(): void
	{
		$isSearch           = is_search();
		$quickSearchEnabled = $this->settings->isQuickSearchEnabled();

		if (!$isSearch && !$quickSearchEnabled) {
			return;
		}

		$host        = $this->settings->getRemote();
		$frontendHost = $this->settings->getFrontendHost();
		$collection  = $this->settings->getCollectionName();
		$searchKey   = $this->settings->getSearchKey();

		$config = [
			'host'                    => esc_url_raw($frontendHost ?: $host),
			'collection'              => sanitize_text_field($collection),
			'searchKey'               => sanitize_text_field($searchKey),
			'hitsPerPage'             => $this->settings->getHitsPerPage(),
			'debounce'                => $this->settings->isDebounceEnabled(),
			'debounceDelay'           => $this->settings->getDebounceDelay(),
			'highlightAffixNumTokens' => $this->settings->getHighlightAffixNumTokens(),
			'truncator'               => sanitize_text_field($this->settings->getTruncator()),
			'sortDisplay'             => $this->settings->getSortDisplay(),
			'webAwesomeLocale'        => self::webAwesomeLocale(),
		];

		// Include configured facets (field, label, placeholder, display_as)
		$facets = [];
		foreach ($this->settings->getFacets() as $f) {
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
			wp_add_inline_script(
				'typesense-search',
				'var typesenseConfig = ' . wp_json_encode($config) . ';',
				'before'
			);
		}

		if ($quickSearchEnabled) {
			$config['quickSearchHitsPerPage'] = $this->settings->getQuickSearchHitsPerPage();

			wp_add_inline_script(
				'typesense-quick-search',
				'var typesenseConfig = ' . wp_json_encode($config) . ';',
				'before'
			);

			// Build selectors list from saved option
			$selectors = array_values(array_filter(array_map(
				function ($s) {
					if (!is_array($s)) {
						return null;
					}
					$selector = sanitize_text_field($s['selector'] ?? '');
					if (empty($selector)) {
						return null;
					}
					return [
						'selector' => $selector,
						'sibling'  => !empty($s['sibling']),
					];
				},
				$this->settings->getQuickSearchSelectors(),
			)));

			wp_add_inline_script(
				'typesense-quick-search',
				'var typesenseQuickSearchConfig = ' . wp_json_encode(['selectors' => $selectors]) . ';',
				'before'
			);
		}
	}
}

