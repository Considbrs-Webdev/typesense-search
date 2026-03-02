@extends('templates.master')

@section('layout')

    {{-- Page heading --}}
    <section class="o-container u-margin__top--6">
        <div class="o-grid">
            <div class="o-grid-12">
                <h1 class="ts-search__heading">
                    {{ $lang->search }}
                </h1>
            </div>
        </div>
    </section>

    {{-- Search interface --}}
    <section class="o-container u-margin__top--2 u-margin__bottom--8" data-js-search-page-container>
        <div class="o-grid">
            <div class="o-grid-12">

                <form class="ts-search" method="get" action="{{ $homeUrl ?? '/' }}">

                    <div class="wa-stack wa-gap-xl">

                        {{-- Search input --}}
                        <div class="ts-main-filter">
                            <div class="ts-search-input">
                                <wa-input size="large" placeholder="{{ $lang->searchPlaceholder }}" with-clear
                                    data-js-search-page-search-input name="s">
                                    <wa-icon name="search" slot="start"></wa-icon>
                                </wa-input>
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

                        {{-- Two-column layout: results (left) + filter sidebar (right) --}}
                        <div class="ts-search-layout">

                            {{-- Left / main: results, no-hits, pagination --}}
                            <div class="ts-search-main wa-stack">
                                <div class="wa-stack ts-results-container" data-js-search-results-container>
                                    <div class="search-meta" data-js-hits-meta>
                                        <h2>{{ $lang->searchResults }}</h2>
                                        <span data-js-search-results-count data-lang-singular="%d träff"
                                            data-lang-plural="%d träffar"></span>
                                    </div>
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

                                <div class="wa-stack" data-js-facets-container></div>
                                <wa-select size="medium" label="{{ $lang->sort }}" value="relevance" data-js-sort>
                                    <wa-option value="relevance">{{ $lang->relevance }}</wa-option>
                                    <wa-option value="dateDesc">{{ $lang->dateDesc }}</wa-option>
                                    <wa-option value="dateAsc">{{ $lang->dateAsc }}</wa-option>
                                </wa-select>

                            </aside>

                        </div>
                    </div>

                </form>

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
