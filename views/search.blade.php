@extends('templates.master')

@section('layout')

    {{-- Stub data – remove when controller provides real values --}}
    @php
        $pageSections = $pageSections ?? [
            'news' => 'News',
            'services' => 'Services',
            'about' => 'About the municipality',
            'events' => 'Events',
            'documents' => 'Documents',
        ];

        $postTypes = $postTypes ?? [
            'post' => 'News article',
            'page' => 'Page',
            'event' => 'Event',
            'document' => 'Document',
        ];

        $activeSections = $activeSections ?? [];
        $activePostTypes = $activePostTypes ?? [];
    @endphp

    {{-- Page heading --}}
    <section class="o-container u-margin__top--6">
        <div class="o-grid">
            <div class="o-grid-12">
                <h1 class="ts-search__heading">
                    {{ $lang->searchResults ?? __('Search', 'typesense-search') }}
                </h1>
            </div>
        </div>
    </section>

    {{-- Search interface --}}
    <section class="o-container u-margin__top--4 u-margin__bottom--8">
        <div class="o-grid">
            <div class="o-grid-12">

                <form class="ts-search" method="get" action="{{ $homeUrl ?? '/' }}">

                    {{-- Search field + submit --}}
                    <div class="ts-search__bar">
                        <label class="u-visually-hidden" for="ts-search-field">
                            {{ $lang->searchPlaceholder ?? __('Search', 'typesense-search') }}
                        </label>
                        <div class="ts-search__input-wrap">
                            <svg class="ts-search__input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                aria-hidden="true" focusable="false">
                                <path
                                    d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                            </svg>
                            <input id="ts-search-field" class="ts-search__input" type="search" name="s"
                                value="{{ $searchQuery ?? '' }}"
                                placeholder="{{ $lang->searchPlaceholder ?? __('Search…', 'typesense-search') }}"
                                autocomplete="off">
                        </div>
                        <button class="ts-search__submit" type="submit">
                            {{ $lang->search ?? __('Search', 'typesense-search') }}
                        </button>
                    </div>

                    {{-- Filters row --}}
                    <div class="ts-search__filters u-margin__top--4">

                        {{-- Page sections --}}
                        @if (!empty($pageSections))
                            <div class="ts-search__filter-group">
                                <span class="ts-search__filter-label">
                                    {{ $lang->filterBySections ?? __('Page sections', 'typesense-search') }}
                                </span>
                                <div class="ts-search__chips">
                                    @foreach ($pageSections as $sectionKey => $sectionLabel)
                                        <button
                                            class="ts-search__chip {{ in_array($sectionKey, $activeSections) ? 'ts-search__chip--active' : '' }}"
                                            type="button" name="sections[]" value="{{ $sectionKey }}"
                                            data-filter-type="section"
                                            aria-pressed="{{ in_array($sectionKey, $activeSections) ? 'true' : 'false' }}">{{ $sectionLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Post types --}}
                        @if (!empty($postTypes))
                            <div class="ts-search__filter-group u-margin__top--3">
                                <span class="ts-search__filter-label">
                                    {{ $lang->filterByPostType ?? __('Post types', 'typesense-search') }}
                                </span>
                                <div class="ts-search__chips">
                                    @foreach ($postTypes as $typeKey => $typeLabel)
                                        <button
                                            class="ts-search__chip {{ in_array($typeKey, $activePostTypes) ? 'ts-search__chip--active' : '' }}"
                                            type="button" name="post_type[]" value="{{ $typeKey }}"
                                            data-filter-type="post-type"
                                            aria-pressed="{{ in_array($typeKey, $activePostTypes) ? 'true' : 'false' }}">{{ $typeLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- Sort --}}
                    <div class="ts-search__sort u-margin__top--4">
                        <label class="ts-search__sort-label" for="ts-search-sort">
                            {{ $lang->sortBy ?? __('Sort by', 'typesense-search') }}
                        </label>
                        <select class="ts-search__sort-select" id="ts-search-sort" name="orderby">
                            <option value="relevance"
                                {{ ($currentSort ?? 'relevance') === 'relevance' ? 'selected' : '' }}>
                                {{ $lang->sortRelevance ?? __('Relevance', 'typesense-search') }}
                            </option>
                            <option value="date_desc" {{ ($currentSort ?? '') === 'date_desc' ? 'selected' : '' }}>
                                {{ $lang->sortDateNewest ?? __('Date (newest first)', 'typesense-search') }}
                            </option>
                            <option value="date_asc" {{ ($currentSort ?? '') === 'date_asc' ? 'selected' : '' }}>
                                {{ $lang->sortDateOldest ?? __('Date (oldest first)', 'typesense-search') }}
                            </option>
                        </select>
                    </div>

                </form>

            </div>
        </div>
    </section>

@stop
