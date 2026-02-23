@extends('templates.master')

@section('layout')

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

                    <div class="wa-stack wa-gap-xl">
                        <div>
                            <wa-input size="large" placeholder="{{ $lang->search }}.." size="small" with-clear>
                                <wa-icon name="search" slot="start"></wa-icon>
                            </wa-input>
                        </div>

                        <div class="wa-split">
                            <div class="wa-cluster">
                                <wa-select size="large" label="Avdelning" placeholder="Välj avdelning" multiple
                                    with-clear>
                                    <wa-option value="option-1">Option 1</wa-option>
                                    <wa-option value="option-2">Option 2</wa-option>
                                    <wa-option value="option-3">Option 3</wa-option>
                                    <wa-option value="option-4">Option 4</wa-option>
                                    <wa-option value="option-5">Option 5</wa-option>
                                    <wa-option value="option-6">Option 6</wa-option>
                                </wa-select>

                                <wa-select size="large" label="Typ" placeholder="Välj typ" multiple with-clear>
                                    <wa-option value="option-1">Option 1</wa-option>
                                    <wa-option value="option-2">Option 2</wa-option>
                                    <wa-option value="option-3">Option 3</wa-option>
                                    <wa-option value="option-4">Option 4</wa-option>
                                    <wa-option value="option-5">Option 5</wa-option>
                                    <wa-option value="option-6">Option 6</wa-option>
                                </wa-select>
                            </div>

                            <wa-select size="large" label="Sortera" value="relevance">
                                <wa-option value="relevance">Relevance</wa-option>
                                <wa-option value="dateAsc">Date Ascending</wa-option>
                                <wa-option value="dateDesc">Date Descending</wa-option>
                            </wa-select>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </section>

@stop
