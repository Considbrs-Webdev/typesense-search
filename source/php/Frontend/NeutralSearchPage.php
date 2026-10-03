<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Keeps the server-rendered search page independent of the search term.
 *
 * Results are fetched client side, so the HTML is identical for every term and
 * can be cached once. The term would otherwise leak into the cached document
 * title, Open Graph/JSON-LD data and the search feed link. The frontend script
 * sets the real title from the URL (see TypesenseConfig::documentTitleConfig).
 */
class NeutralSearchPage
{
	private SettingsRepository $settings;

	public function __construct(SettingsRepository $settings)
	{
		$this->settings = $settings;

		add_filter('get_search_query', [$this, 'blankSearchQuery']);
		add_filter('the_seo_framework_title_from_generation', [$this, 'neutralTitle']);
		add_action('template_redirect', [$this, 'removeSearchFeedLink']);
		add_action('template_redirect', [$this, 'preventCachingFallback']);
		add_filter('posts_pre_query', [$this, 'skipMainSearchQuery'], 10, 2);
	}

	private function isActive(): bool
	{
		return !is_admin() && is_search() && $this->settings->canUseTypesense();
	}

	/**
	 * Return an empty search query everywhere on the frontend search page.
	 */
	public function blankSearchQuery($query)
	{
		return $this->isActive() ? '' : $query;
	}

	/**
	 * Use a plain "Search" title instead of "Search results for “…”".
	 */
	public function neutralTitle($title)
	{
		return $this->isActive() ? __('Search', 'typesense-search') : $title;
	}

	/**
	 * A feed of search results is meaningless when the results render client side.
	 */
	public function removeSearchFeedLink(): void
	{
		if ($this->isActive()) {
			remove_action('wp_head', 'feed_links_extra', 3);
		}
	}

	/**
	 * Skip the main WordPress search query; Typesense renders the results.
	 *
	 * Only when Typesense is ready, so the regular WordPress search remains the
	 * fallback otherwise.
	 *
	 * @param array<int, \WP_Post>|null $posts
	 * @return array<int, \WP_Post>|null
	 */
	public function skipMainSearchQuery($posts, $query)
	{
		if (
			!$query instanceof \WP_Query
			|| !$query->is_main_query()
			|| !$this->isActive()
			|| !apply_filters('typesense_search/skip_main_search_query', true)
			|| !ClientFactory::isReadyWithCollection()
		) {
			return $posts;
		}

		$query->found_posts   = 0;
		$query->max_num_pages = 0;

		return [];
	}

	/**
	 * Don't let page caches store the search page when Typesense is not ready.
	 *
	 * The fallback page is the regular WordPress search page; caching it would
	 * keep serving it after Typesense is back.
	 */
	public function preventCachingFallback(): void
	{
		if (!$this->isActive() || ClientFactory::isReadyWithCollection()) {
			return;
		}

		nocache_headers();
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('X-Accel-Expires: 0');
	}
}
