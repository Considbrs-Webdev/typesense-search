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
    <section class="o-container u-margin__top--4 u-margin__bottom--8" data-js-search-page-container>
        <div class="o-grid">
            <div class="o-grid-12">

                <form class="ts-search" method="get" action="{{ $homeUrl ?? '/' }}">

                    <div class="wa-stack">
                        <div class="wa-stack wa-gap-xl ts-main-filter">
                            <div>
                                <wa-input size="large" placeholder="{{ $lang->searchPlaceholder }}" size="small" with-clear
                                    data-js-search-page-search-input name="s">
                                    <wa-icon name="search" slot="start"></wa-icon>
                                </wa-input>
                            </div>

                            <div class="wa-split">
                                <div class="wa-cluster" data-js-facets-container>

                                </div>

                                <wa-select size="medium" label="{{ $lang->sort }}" value="relevance" data-js-sort>
                                    <wa-option value="relevance">{{ $lang->relevance }}</wa-option>
                                    <wa-option value="dateDesc">{{ $lang->dateDesc }}</wa-option>
                                    <wa-option value="dateAsc">{{ $lang->dateAsc }}</wa-option>
                                </wa-select>
                            </div>

                            <div class="wa-stack" data-js-search-results-container>
                                <div class="search-meta">
                                    <h2>{{ $lang->searchResults }}</h2>
                                    <span data-js-search-results-count data-lang-singular="%d träff"
                                        data-lang-plural="%d träffar"></span>
                                </div>
                                <div class="ts-search-results wa-stack" data-js-search-results>

                                </div>
                            </div>
                        </div>

                        <div class="ts-pagination" data-js-search-pagination></div>
                    </div>

                </form>

                @include('templates.pagination')

                {{-- Render compiled hit templates for JS consumption --}}
                @if (!empty($hitTemplates))
                    @foreach ($hitTemplates as $template)
                        @include("templates.hits.{$template}")
                    @endforeach
                @endif

            </div>
        </div>
    </section>

@stop
