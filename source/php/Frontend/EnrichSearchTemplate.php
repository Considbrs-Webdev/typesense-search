<?php

namespace TypesenseSearch\Frontend;

use stdClass;

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
			'searchResults' => __("Search Results", 'typesense-search'),
			'department' => __("Department", 'typesense-search'),
			'departmentPlaceholder' => __("Choose department", 'typesense-search'),
			'type' => __("Type", 'typesense-search'),
			'typePlaceholder' => __("Choose type", 'typesense-search'),
			'sort' => __("Sort", 'typesense-search'),
			'relevance' => __("Relevance", 'typesense-search'),
			'dateAsc' => __("Date Ascending", 'typesense-search'),
			'dateDesc' => __("Date Descending", 'typesense-search'),
		]);

		$data['lang'] = (object)$lang;

		// Home URL used by the search form
		$data['homeUrl'] = $data['homeUrl'] ?? home_url('/');

		// Load templates from views/templates
		$pluginRoot = dirname(__DIR__, 3);
		$templatesDir = $pluginRoot . '/views/templates/hits';
		$templates = [];

		if (is_dir($templatesDir)) {
			foreach (glob($templatesDir . '/*.blade.php') as $file) {
				$name = basename($file, '.blade.php');
				$templates[] = $name;
			}
		}

		$data['hitTemplates'] = $templates;

		return $data;
	}
}

