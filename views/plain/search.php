<?php
/**
 * Search page markup. Mirrors views/search.blade.php; the JavaScript hooks on
 * the data-js-* attributes, so keep them when overriding.
 *
 * @var object               $lang
 * @var string               $homeUrl
 * @var string               $sortDisplay
 * @var string[]             $hitTemplates
 */

use TypesenseSearch\Frontend\PlainSearchTemplate;

if (!defined('WPINC')) {
    die;
}
?>
<section class="ts-plain__container">
    <h1 class="ts-search__heading"><?php echo esc_html($lang->searchTitle); ?></h1>
</section>

<noscript>
    <form class="ts-search ts-nojs" method="get" action="<?php echo esc_url($homeUrl ?? home_url('/')); ?>">
        <p><?php echo esc_html($lang->noJsNotice); ?></p>
        <label for="ts-nojs-field"><?php echo esc_html($lang->searchLabel); ?></label>
        <input id="ts-nojs-field" type="search" name="s" required>
        <input type="hidden" name="<?php echo esc_attr($lang->noJsArg); ?>" value="1">
        <button type="submit"><?php echo esc_html($lang->search); ?></button>
    </form>
</noscript>

<section class="ts-plain__container" data-js-search-page-container aria-busy="true">
    <form class="ts-search" method="get" action="<?php echo esc_url($homeUrl ?? home_url('/')); ?>">
        <div class="wa-stack wa-gap-xl">
            <div class="ts-search-layout">

                <div class="ts-search-main wa-stack">

                    <div class="ts-main-filter">
                        <div class="ts-search-row">
                            <div class="ts-search-input-field">
                                <wa-input id="ts-search-field" size="large"
                                    label="<?php echo esc_attr($lang->searchLabel); ?>"
                                    placeholder="<?php echo esc_attr($lang->searchPlaceholder); ?>" with-clear
                                    data-js-search-page-search-input name="s">
                                </wa-input>
                            </div>
                            <button type="submit" class="ts-search-btn"><?php echo esc_html($lang->search); ?></button>
                        </div>
                    </div>

                    <div class="ts-filter-toggle-bar">
                        <button type="button" class="ts-filter-toggle-btn" data-js-filter-toggle>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                <path d="M1.5 3h13M4 8h8M6.5 13h3" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" fill="none" />
                            </svg>
                            <?php echo esc_html($lang->filter); ?>
                        </button>
                    </div>

                    <div class="wa-stack ts-results-container" data-js-search-results-container>
                        <p class="ts-search-summary" data-js-search-summary
                            data-lang-template="<?php echo esc_attr($lang->searchSummaryTemplate); ?>"
                            data-lang-plural="<?php echo esc_attr($lang->resultPlural); ?>"
                            role="status" aria-live="polite" aria-atomic="true"></p>

                        <span data-js-search-results-count data-lang-singular="<?php echo esc_attr($lang->resultSingular); ?>"
                            data-lang-plural="<?php echo esc_attr($lang->resultPlural); ?>" hidden></span>

                        <ol class="ts-search-results wa-stack" data-js-search-results role="list"></ol>
                    </div>

                    <div class="wa-stack ts-no-hits-container" data-js-no-hits-container>
                        <div class="no-hits">
                            <h2><?php echo esc_html($lang->noResults); ?></h2>
                            <span><?php echo esc_html($lang->noResultsMessage); ?></span>
                        </div>
                    </div>

                    <div class="ts-pagination" data-js-search-pagination></div>
                </div>

                <aside class="ts-filter-sidebar wa-stack" data-js-filter-sidebar>
                    <div class="ts-filter-sidebar__header">
                        <span class="ts-sidebar-results-count" data-js-sidebar-results-count></span>
                        <button type="button" class="ts-filter-close-btn" data-js-filter-close
                            aria-label="<?php echo esc_attr($lang->closeFilter); ?>">&times;</button>
                    </div>

                    <?php if (($sortDisplay ?? 'radio') === 'dropdown') : ?>
                        <wa-select size="medium" label="<?php echo esc_attr($lang->sortBy); ?>" value="relevance" data-js-sort>
                            <wa-option value="relevance"><?php echo esc_html($lang->relevance); ?></wa-option>
                            <wa-option value="dateDesc"><?php echo esc_html($lang->dateDesc); ?></wa-option>
                            <wa-option value="dateAsc"><?php echo esc_html($lang->dateAsc); ?></wa-option>
                        </wa-select>
                    <?php else : ?>
                        <div class="ts-sort-section">
                            <h3 id="ts-sort-heading" class="ts-sort-heading"><?php echo esc_html($lang->sortBy); ?></h3>
                            <wa-radio-group value="relevance" data-js-sort aria-labelledby="ts-sort-heading">
                                <wa-radio value="relevance"><?php echo esc_html($lang->relevance); ?></wa-radio>
                                <wa-radio value="dateDesc"><?php echo esc_html($lang->dateDesc); ?></wa-radio>
                                <wa-radio value="dateAsc"><?php echo esc_html($lang->dateAsc); ?></wa-radio>
                            </wa-radio-group>
                        </div>
                    <?php endif; ?>

                    <div class="wa-stack" data-js-facets-container></div>
                </aside>

            </div>
        </div>
    </form>

    <div class="ts-loader" data-js-loader role="status" aria-live="polite">
        <span class="ts-sr-only"><?php echo esc_html($lang->loadingSearch); ?></span>
    </div>

    <div class="ts-filter-overlay" data-js-filter-overlay></div>

    <?php PlainSearchTemplate::render('pagination.php', ['lang' => $lang]); ?>

    <?php foreach (($hitTemplates ?? []) as $templateKey) : ?>
        <?php PlainSearchTemplate::render('hits/hit-' . sanitize_key($templateKey) . '.php'); ?>
    <?php endforeach; ?>
</section>
