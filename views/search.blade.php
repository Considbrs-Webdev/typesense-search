@extends('templates.master')

@section('layout')

    {{-- Page heading --}}
    <section class="o-container u-margin__top--6">
        <div class="o-grid">
            <div class="o-grid-12">
                <h1 class="ts-search__heading" hidden>
                    {{ $lang->search }}
                </h1>
            </div>
        </div>
    </section>

    {{-- Search interface --}}
    <section class="o-container u-margin__top--2 u-margin__bottom--8" data-js-search-page-container>
        <div class="o-grid">
            <div class="o-grid-12">

                <form class="ts-search" method="get" action="{{ $homeUrl ?? '/' }}" hidden>

                    <div class="wa-stack wa-gap-xl">

                        {{-- Two-column layout: results (left) + filter sidebar (right) --}}
                        <div class="ts-search-layout">

                            {{-- Left / main: results, no-hits, pagination --}}
                            <div class="ts-search-main wa-stack">

                                {{-- Search input --}}
                                <div class="ts-main-filter">
                                    <div class="ts-search-row">
                                        <div class="ts-search-input-field">
                                            <label class="ts-search-label"
                                                for="ts-search-field">{{ $lang->searchLabel }}</label>
                                            <wa-input id="ts-search-field" size="large"
                                                placeholder="{{ $lang->searchPlaceholder }}" with-clear
                                                data-js-search-page-search-input name="s">
                                            </wa-input>
                                        </div>
                                        <button type="submit" class="ts-search-btn">{{ $lang->search }}</button>
                                    </div>
                                </div>

                                {{-- Mobile: filter toggle button --}}
                                <div class="ts-filter-toggle-bar">
                                    <button type="button" class="ts-filter-toggle-btn" data-js-filter-toggle>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true"
                                            focusable="false">
                                            <path d="M1.5 3h13M4 8h8M6.5 13h3" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round" fill="none" />
                                        </svg>
                                        {{ $lang->filter }}
                                    </button>
                                </div>

                                <div class="wa-stack ts-results-container" data-js-search-results-container>

                                    {{-- Summary sentence: "Din sökning X gav Y träffar" (populated by JS) --}}
                                    <p class="ts-search-summary" data-js-search-summary
                                        data-lang-template="{{ $lang->searchSummaryTemplate }}" hidden></p>

                                    {{-- Count span: used internally by search.ts + as data source for summary --}}
                                    <span data-js-search-results-count data-lang-singular="{{ $lang->resultSingular }}"
                                        data-lang-plural="{{ $lang->resultPlural }}" hidden></span>

                                    <div class="ts-search-results wa-stack" data-js-search-results>
                                    </div>
                                </div>

                                <div class="wa-stack ts-no-hits-container" data-js-no-hits-container>
                                    <div class="no-hits">
                                        <h2>{{ $lang->noResults }}</h2>
                                        <span>{{ $lang->noResultsMessage }}</span>
                                    </div>
                                </div>

                                <div class="ts-pagination" data-js-search-pagination></div>
                            </div>

                            {{-- Right: filter sidebar. On desktop: sticky column. On mobile: fixed slide-in panel. --}}
                            <aside class="ts-filter-sidebar wa-stack" data-js-filter-sidebar>

                                {{-- Mobile-only header: result count + close button --}}
                                <div class="ts-filter-sidebar__header">
                                    <span class="ts-sidebar-results-count" data-js-sidebar-results-count></span>
                                    <button type="button" class="ts-filter-close-btn" data-js-filter-close
                                        aria-label="{{ $lang->closeFilter }}">&times;</button>
                                </div>

                                {{-- Sort — style controlled by admin setting (sortDisplay: 'radio'|'dropdown') --}}
                                @if (($sortDisplay ?? 'radio') === 'dropdown')
                                    <wa-select size="medium" label="{{ $lang->sortBy }}" value="relevance" data-js-sort>
                                        <wa-option value="relevance">{{ $lang->relevance }}</wa-option>
                                        <wa-option value="dateDesc">{{ $lang->dateDesc }}</wa-option>
                                        <wa-option value="dateAsc">{{ $lang->dateAsc }}</wa-option>
                                    </wa-select>
                                @else
                                    <div class="ts-sort-section">
                                        <h3 class="ts-sort-heading">{{ $lang->sortBy }}</h3>
                                        <wa-radio-group value="relevance" data-js-sort>
                                            <wa-radio value="relevance">{{ $lang->relevance }}</wa-radio>
                                            <wa-radio value="dateDesc">{{ $lang->dateDesc }}</wa-radio>
                                            <wa-radio value="dateAsc">{{ $lang->dateAsc }}</wa-radio>
                                        </wa-radio-group>
                                    </div>
                                @endif

                                {{-- Facet filters --}}
                                <div class="wa-stack" data-js-facets-container></div>

                            </aside>

                        </div>
                    </div>

                </form>

                <div class="ts-loader" data-js-loader hidden></div>

                {{-- Backdrop overlay for mobile filter panel --}}
                <div class="ts-filter-overlay" data-js-filter-overlay></div>

                @include('templates.pagination')

                {{-- Render compiled hit templates for JS consumption --}}
                @if (!empty($hitTemplates) && !empty($hitTemplateViews))
                    @foreach ($hitTemplates as $templateKey)
                        @php $viewPath = $hitTemplateViews[$templateKey] ?? "templates.hits.{$templateKey}"; @endphp
                        @include($viewPath)
                    @endforeach
                @endif

            </div>
        </div>
    </section>

@stop
