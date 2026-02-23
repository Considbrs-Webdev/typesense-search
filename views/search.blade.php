@extends('templates.master')

@section('layout')

    {{-- Page heading --}}
    <section class="o-container u-margin__top--6">
        <div class="o-grid">
            <div class="o-grid-12">
                <h1 class="ts-search__heading">
                    {{ $lang->searchResults }}
                </h1>
            </div>
        </div>
    </section>

    {{-- Search interface --}}
    <section class="o-container u-margin__top--4 u-margin__bottom--8" data-js-search-page-container>
        <div class="o-grid">
            <div class="o-grid-12">

                <form class="ts-search" method="get" action="{{ $homeUrl ?? '/' }}">

                    <div class="wa-stack wa-gap-xl">
                        <div>
                            <wa-input size="large" placeholder="{{ $lang->search }}.." size="small" with-clear
                                data-js-search-page-search-input name="s">
                                <wa-icon name="search" slot="start"></wa-icon>
                            </wa-input>
                        </div>

                        <div class="wa-split">
                            <div class="wa-cluster">
                                <wa-select size="large" label="{{ $lang->department ?? 'Avdelning' }}"
                                    placeholder="{{ $lang->departmentPlaceholder ?? 'Välj avdelning' }}" multiple
                                    with-clear>
                                    <wa-option value="option-1">Option 1</wa-option>
                                    <wa-option value="option-2">Option 2</wa-option>
                                    <wa-option value="option-3">Option 3</wa-option>
                                    <wa-option value="option-4">Option 4</wa-option>
                                    <wa-option value="option-5">Option 5</wa-option>
                                    <wa-option value="option-6">Option 6</wa-option>
                                </wa-select>

                                <wa-select size="large" label="{{ $lang->type ?? 'Typ' }}"
                                    placeholder="{{ $lang->typePlaceholder ?? 'Välj typ' }}" multiple with-clear>
                                    <wa-option value="option-1">Option 1</wa-option>
                                    <wa-option value="option-2">Option 2</wa-option>
                                    <wa-option value="option-3">Option 3</wa-option>
                                    <wa-option value="option-4">Option 4</wa-option>
                                    <wa-option value="option-5">Option 5</wa-option>
                                    <wa-option value="option-6">Option 6</wa-option>
                                </wa-select>
                            </div>

                            <wa-select size="large" label="{{ $lang->sort }}" value="relevance">
                                <wa-option value="relevance">{{ $lang->relevance }}</wa-option>
                                <wa-option value="dateAsc">{{ $lang->dateAsc }}</wa-option>
                                <wa-option value="dateDesc">{{ $lang->dateDesc }}</wa-option>
                            </wa-select>
                        </div>

                        <div class="ts-search-results wa-stack" data-js-search-results>

                        </div>

                        <div class="ts-pagination" data-js-search-pagination>
                        </div>
                    </div>

                </form>

                @include('templates.pagination')

                {{-- Render compiled hit templates for JS consumption --}}
                @if (!empty($hitTemplates))
                    @foreach ($hitTemplates as $name => $template)
                        {!! $template !!}
                    @endforeach
                @endif

            </div>
        </div>
    </section>

@stop
