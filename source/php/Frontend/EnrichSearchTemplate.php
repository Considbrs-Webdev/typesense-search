<?php

namespace TypesenseSearch\Frontend;

use stdClass;
use TypesenseSearch\Admin\Settings;

/**
 * Enrich view data for the search template with translations and templates
 */
class EnrichSearchTemplate
{
	public function __construct()
	{
		add_filter('Municipio/Template/viewData', [$this, 'addTypesenseViewData'], 10, 1);
	}

	/**
	 * Add translations, home URL and compiled template HTML to the view data.
	 *
	 * @param array $data
	 * @return array
	 */
	public function addTypesenseViewData(array $data): array
	{
		if (!is_search()) {
			return $data;
		}

		// Ensure a place for the plugin data
		$data['typesense'] = $data['typesense'] ?? [];

		// Language labels (merge with any existing lang data)
		$existingLang = (array)($data['lang'] ?? []);
		$lang = array_merge($existingLang, [
			'search' => __("Search", 'typesense-search'),
			'searchLabel' => __("Enter search term", 'typesense-search'),
			'searchPlaceholder' => __("Search term here..", 'typesense-search'),
			'searchSummaryTemplate' => __("Your search for %1\$s returned %2\$s", 'typesense-search'),
			'resultSingular' => __("%d result", 'typesense-search'),
			'resultPlural' => __("%d results", 'typesense-search'),
			'noResults' => __("No Results", 'typesense-search'),
			'noResultsMessage' => __("No results found for your search.", 'typesense-search'),
			'sort' => __("Sort", 'typesense-search'),
			'sortBy' => __("Sort by", 'typesense-search'),
			'relevance' => __("Relevance", 'typesense-search'),
			'dateAsc' => __("Date Ascending", 'typesense-search'),
			'dateDesc' => __("Date Descending", 'typesense-search'),
			'filter' => __("Filter", 'typesense-search'),
			'closeFilter' => __("Close filter", 'typesense-search'),
		]);

		$data['lang'] = (object)$lang;

		// Sort control style: 'radio' | 'dropdown' — controlled by admin setting
		$sortDisplay = get_option(Settings::OPTION_SORT_DISPLAY, 'radio');
		$data['sortDisplay'] = in_array($sortDisplay, ['radio', 'dropdown'], true) ? $sortDisplay : 'radio';

		// Home URL used by the search form
		$data['homeUrl'] = $data['homeUrl'] ?? home_url('/');

		// Built-in template keys (match data-js-search-hit-template-{key} convention)
		$templates = ['default', 'noimage', 'jobposting'];

		/**
		 * Filters the list of hit template keys.
		 *
		 * @param string[] $templates Template keys (e.g. 'default', 'image', 'simpleview-event').
		 */
		$data['hitTemplates'] = (array) apply_filters('Municipio/TypesenseSearch/hitTemplates', $templates);

		// Resolve view path for each template
		$builtInViews = [
			'default'       => 'templates.hits.hit-default',
			'noimage'       => 'templates.hits.hit-noimage',
			'jobposting'    => 'templates.hits.hit-jobposting',
		];
		$data['hitTemplateViews'] = [];
		foreach ($data['hitTemplates'] as $key) {
			$defaultView = $builtInViews[$key] ?? 'templates.hits.' . $key;
			/**
			 * Filters the view path for a hit template.
			 *
			 * @param string $view  Resolved view path (e.g. 'templates.hits.hit-default').
			 * @param string $key  Template key (e.g. 'default', 'simpleview-event').
			 */
			$data['hitTemplateViews'][$key] = (string) apply_filters('Municipio/TypesenseSearch/hitTemplateView', $defaultView, $key);
		}

		return $data;
	}
}
