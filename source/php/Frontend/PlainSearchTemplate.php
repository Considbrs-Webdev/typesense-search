<?php

namespace TypesenseSearch\Frontend;

use TypesenseSearch\Services\SettingsRepository;
use TypesenseSearch\Typesense\ClientFactory;

/**
 * Renders the search page with plain PHP views on sites without Municipio.
 *
 * Municipio sites keep using the Blade views registered in Templates. Elsewhere
 * the theme's search template is replaced by views/plain/search.php, which can
 * be overridden by copying it to `typesense-search/search.php` in the theme.
 * Hit templates can be overridden the same way via
 * `typesense-search/hits/hit-{key}.php`.
 */
class PlainSearchTemplate
{
	private static ?self $instance = null;

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly EnrichSearchTemplate $viewData
	) {
		self::$instance = $this;

		add_filter('template_include', [$this, 'maybeUsePlainTemplate'], 99);
		add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
	}

	/**
	 * Whether the plain PHP search page should be used for this request.
	 */
	public function isActive(): bool
	{
		if (is_admin() || !is_search() || !$this->settings->canUseTypesense() || NoJsFallback::isRequested() || !ClientFactory::isReadyWithCollection()) {
			return false;
		}

		/**
		 * Filters whether the plain PHP search template is used.
		 *
		 * Defaults to true unless the Municipio theme renders the page.
		 *
		 * @param bool $use
		 */
		return (bool) apply_filters('typesense_search/use_plain_template', !self::isMunicipioActive());
	}

	public static function isMunicipioActive(): bool
	{
		return class_exists('\\Municipio\\App') || wp_get_theme()->get_template() === 'municipio';
	}

	/**
	 * @param string $template Template path chosen by WordPress.
	 */
	public function maybeUsePlainTemplate($template)
	{
		if (!$this->isActive()) {
			return $template;
		}

		if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
			$this->useBlockThemeTemplate();

			return $template;
		}

		return TYPESENSESEARCH_VIEW_PATH . '/plain/template.php';
	}

	/**
	 * Render the search page inside the block theme's own template canvas.
	 *
	 * Block themes have no header.php/footer.php, so get_header() would fall
	 * back to the legacy markup. Instead the page is wrapped by the theme's
	 * header and footer template parts.
	 */
	private function useBlockThemeTemplate(): void
	{
		global $_wp_current_template_content;

		ob_start();
		self::render('search.php', self::getViewData());
		$html = (string) ob_get_clean();

		/**
		 * Filters the template part slugs wrapped around the search page in block themes.
		 *
		 * @param array{header: string, footer: string} $parts
		 */
		$parts = (array) apply_filters('typesense_search/block_template_parts', ['header' => 'header', 'footer' => 'footer']);

		$_wp_current_template_content = sprintf(
			'<!-- wp:template-part {"slug":"%1$s","tagName":"header"} /-->'
			. '<!-- wp:group {"tagName":"main","className":"ts-plain","layout":{"type":"constrained"}} -->'
			. '<main class="wp-block-group ts-plain"><!-- wp:html -->%2$s<!-- /wp:html --></main>'
			. '<!-- /wp:group -->'
			. '<!-- wp:template-part {"slug":"%3$s","tagName":"footer"} /-->',
			esc_attr($parts['header'] ?? 'header'),
			$html,
			esc_attr($parts['footer'] ?? 'footer')
		);
	}

	public function enqueueStyles(): void
	{
		if (!$this->isActive()) {
			return;
		}

		$cssFile = \TypesenseSearch\Helper\CacheBust::name('css/typesense-search-plain.css') ?: 'css/typesense-search-plain.css';

		if (!file_exists(TYPESENSESEARCH_PATH . 'assets/dist/' . $cssFile)) {
			return;
		}

		wp_enqueue_style(
			'typesense-search-plain',
			TYPESENSESEARCH_URL . '/assets/dist/' . $cssFile,
			['typesense-search'],
			null
		);
	}

	/**
	 * View data shared with the plain views (same shape as the Blade view data).
	 *
	 * @return array<string, mixed>
	 */
	public static function getViewData(): array
	{
		return self::$instance ? self::$instance->viewData->addTypesenseViewData([]) : [];
	}

	/**
	 * Locate a view, preferring a theme override.
	 *
	 * @param string $relative Path relative to views/plain, e.g. 'hits/hit-default.php'.
	 */
	public static function locate(string $relative): string
	{
		$override = locate_template('typesense-search/' . $relative);

		if ($override !== '') {
			return $override;
		}

		$path = TYPESENSESEARCH_VIEW_PATH . '/plain/' . $relative;

		return file_exists($path) ? $path : '';
	}

	/**
	 * Include a view with the given variables in scope.
	 *
	 * @param array<string, mixed> $vars
	 */
	public static function render(string $relative, array $vars = []): void
	{
		$file = self::locate($relative);

		if ($file === '') {
			return;
		}

		extract($vars, EXTR_SKIP);
		include $file;
	}
}
