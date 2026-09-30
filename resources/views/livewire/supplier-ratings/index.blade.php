@php
    $starColors = [5 => '#059669', 4 => '#14b8a6', 3 => '#f59e0b', 2 => '#f97316', 1 => '#dc2626'];
    $criteriaLabels = collect(\App\Models\SupplierRating::CRITERIA)->map(fn ($label) => __($label));
    $medal = fn (int $rank) => match ($rank) { 1 => 'boq-rank-gold', 2 => 'boq-rank-silver', 3 => 'boq-rank-bronze', default => '' };
    $barItems = fn (array $rows, string $color) => collect($rows)->map(fn ($row) => [
        'label' => $row['supplier']->name,
        'hint' => trans_choice(':count rating|:count ratings', $row['count'], ['count' => $row['count']]),
        'value' => $row['average'],
        'color' => $color,
        'click' => 'showSupplier('.$row['supplier']->id.')',
    ])->all();
@endphp

<div class="boq-page-stack">
    <x-ui.page-header
        :title="__('Top Rated Suppliers')"
        icon="fa-ranking-star"
        :subtitle="__('Ratings of hardware suppliers and factories by users. Rate the ones you buy from to reward good prices and service.')"
    >
        <x-slot:actions>
            <x-ui.button icon="fa-star" wire:click="openRate">{{ __('Rate a Supplier') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.flash :keys="['message', 'error']" />

    <x-ui.tabs :label="__('Period')">
        @foreach(['week' => [__('This week'), 'fa-calendar-week'], 'month' => [__('This month'), 'fa-calendar-days'], 'year' => [__('This year'), 'fa-calendar'], 'all' => [__('All time'), 'fa-infinity']] as $value => [$label, $icon])
            <x-ui.tab wire:click="setPeriod('{{ $value }}')" wire:key="period-{{ $value }}" :icon="$icon" :active="$period === $value">{{ $label }}</x-ui.tab>
        @endforeach
    </x-ui.tabs>

    <div class="boq-stats-grid">
        <x-stat-card :label="__('Ratings').' · '.$periodLabel" :value="\App\Support\Format::number($summary['total'], 0)" icon="fa-star" color="amber" />
        <x-stat-card :label="__('Average rating')" :value="$summary['average'] !== null ? number_format($summary['average'], 1).' / 5' : '—'" :hint="trans_choice(':count supplier rated|:count suppliers rated', $summary['suppliers'], ['count' => $summary['suppliers']])" icon="fa-star-half-stroke" color="blue" />
        <x-stat-card :label="__('Hardware average')" :value="$summary['by_type']['supplier']['average'] !== null ? number_format($summary['by_type']['supplier']['average'], 1).' / 5' : '—'" :hint="trans_choice(':count rating|:count ratings', $summary['by_type']['supplier']['count'], ['count' => $summary['by_type']['supplier']['count']])" icon="fa-screwdriver-wrench" color="green" />
        <x-stat-card :label="__('Factory average')" :value="$summary['by_type']['factory']['average'] !== null ? number_format($summary['by_type']['factory']['average'], 1).' / 5' : '—'" :hint="trans_choice(':count rating|:count ratings', $summary['by_type']['factory']['count'], ['count' => $summary['by_type']['factory']['count']])" icon="fa-industry" color="purple" />
    </div>

    {{-- Top 10 rankings --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @foreach([['rows' => $topHardware, 'title' => __('Top 10 Hardware'), 'icon' => 'fa-store', 'key' => 'hardware'], ['rows' => $topFactories, 'title' => __('Top 10 Factories'), 'icon' => 'fa-industry', 'key' => 'factories']] as $board)
            <section class="boq-card" wire:key="board-{{ $board['key'] }}">
                <div class="boq-card-header">
                    <h2 class="boq-card-title"><i class="fas {{ $board['icon'] }}" aria-hidden="true"></i> {{ $board['title'] }}</h2>
                    <span class="boq-card-subtitle">{{ $periodLabel }}</span>
                </div>
                @if($board['rows'] === [])
                    <x-ui.empty-state icon="fa-star" :title="__('No ratings in this period yet.')" :description="__('Be the first to rate one.')" />
                @else
                    <div class="boq-table-wrapper">
                        <table class="boq-table">
                            <thead>
                                <tr>
                                    <th class="w-12">#</th>
                                    <th>{{ __('Name') }}</th>
                                    <th class="hidden sm:table-cell">{{ __('Rating') }}</th>
                                    <th class="hidden text-right sm:table-cell"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($board['rows'] as $row)
                                    <tr wire:key="{{ $board['key'] }}-{{ $row['supplier']->id }}">
                                        <td><span class="boq-rank {{ $medal($row['rank']) }}">{{ $row['rank'] }}</span></td>
                                        <td>
                                            <button type="button" wire:click="showSupplier({{ $row['supplier']->id }})" class="boq-table-title boq-table-link text-left">{{ $row['supplier']->name }}</button>
                                            <div class="boq-table-subtitle">{{ $row['supplier']->location ?: $row['supplier']->region ?: '—' }}</div>
                                            <x-stars class="mt-1 sm:hidden" :value="$row['average']" :count="$row['count']" />
                                        </td>
                                        <td class="hidden sm:table-cell"><x-stars :value="$row['average']" :count="$row['count']" /></td>
                                        <td class="hidden text-right sm:table-cell">
                                            <button type="button" wire:click="openRate({{ $row['supplier']->id }})" class="boq-icon-btn" title="{{ __('Rate') }}" aria-label="{{ __('Rate :name', ['name' => $row['supplier']->name]) }}"><i class="fas fa-star"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
    <p class="text-xs text-slate-500">{{ __('Rankings use a weighted score: a supplier needs several good ratings to rank above one with many, so a single 5-star rating does not top the list.') }}</p>

    {{-- Performance charts --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-bar" aria-hidden="true"></i> {{ __('Top hardware · average rating') }}</h2></div>
            <div class="boq-card-body"><x-charts.bar :items="$barItems($topHardware, '#0d9488')" :max="5" :label="__('Top hardware · average rating')" /></div>
        </section>
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-bar" aria-hidden="true"></i> {{ __('Top factories · average rating') }}</h2></div>
            <div class="boq-card-body"><x-charts.bar :items="$barItems($topFactories, '#7c3aed')" :max="5" :label="__('Top factories · average rating')" /></div>
        </section>
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-pie" aria-hidden="true"></i> {{ __('Rating distribution') }}</h2><span class="boq-card-subtitle">{{ $periodLabel }}</span></div>
            <div class="boq-card-body">
                <x-charts.pie :label="__('Rating distribution')"
                    :slices="collect($summary['distribution'])->map(fn ($count, $stars) => ['label' => trans_choice(':count star|:count stars', $stars, ['count' => $stars]), 'value' => $count, 'color' => $starColors[$stars]])->values()->all()"
                    :center="$summary['average'] !== null ? number_format($summary['average'], 1) : null" :center-label="__('average')" />
            </div>
        </section>
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-pie" aria-hidden="true"></i> {{ __('Ratings by type') }}</h2><span class="boq-card-subtitle">{{ $periodLabel }}</span></div>
            <div class="boq-card-body">
                <x-charts.pie :label="__('Ratings by type')"
                    :slices="[['label' => __('Hardware'), 'value' => $summary['by_type']['supplier']['count'], 'color' => '#0d9488'], ['label' => __('Factories'), 'value' => $summary['by_type']['factory']['count'], 'color' => '#7c3aed']]"
                    :center="\App\Support\Format::number($summary['total'], 0)" :center-label="__('ratings')" />
            </div>
        </section>
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-line" aria-hidden="true"></i> {{ __('Monthly trend · hardware') }}</h2><span class="boq-card-subtitle">{{ __('Last 12 months') }}</span></div>
            <div class="boq-card-body"><x-charts.line :points="$hardwareTrend" :label="__('Monthly trend · hardware')" :value-label="__('Average rating')" :count-label="__('Ratings')" /></div>
        </section>
        <section class="boq-card">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-chart-line" aria-hidden="true"></i> {{ __('Monthly trend · factories') }}</h2><span class="boq-card-subtitle">{{ __('Last 12 months') }}</span></div>
            <div class="boq-card-body"><x-charts.line :points="$factoryTrend" :label="__('Monthly trend · factories')" :value-label="__('Average rating')" :count-label="__('Ratings')" /></div>
        </section>
        <section class="boq-card lg:col-span-2">
            <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-list-check" aria-hidden="true"></i> {{ __('Scores by area') }}</h2><span class="boq-card-subtitle">{{ $periodLabel }}</span></div>
            <div class="boq-card-body">
                <x-charts.bar :max="5" :label="__('Scores by area')" :empty="__('No detailed scores in this period yet.')"
                    :items="collect($summary['criteria'])->filter(fn ($v) => $v !== null)->map(fn ($v, $field) => ['label' => $criteriaLabels[$field], 'value' => $v, 'color' => '#2563eb'])->values()->all()" />
            </div>
        </section>
    </div>

    {{-- Admins: who needs to improve --}}
    @if($isAdmin)
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach([['rows' => $lowHardware, 'title' => __('Hardware needing improvement')], ['rows' => $lowFactories, 'title' => __('Factories needing improvement')]] as $board)
                <section class="boq-card">
                    <div class="boq-card-header">
                        <h2 class="boq-card-title"><i class="fas fa-arrow-trend-down" aria-hidden="true"></i> {{ $board['title'] }}</h2>
                        <span class="boq-card-subtitle">{{ __('Lowest rated') }} · {{ $periodLabel }}</span>
                    </div>
                    <div class="boq-card-body">
                        <x-charts.bar :max="5" :label="$board['title']" :items="$barItems($board['rows'], '#dc2626')" />
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    {{-- My ratings --}}
    <section class="boq-card">
        <div class="boq-card-header"><h2 class="boq-card-title"><i class="fas fa-user-check" aria-hidden="true"></i> {{ __('My ratings') }}</h2></div>
        @if($myRatings->isEmpty())
            <x-ui.empty-state icon="fa-star" :title="__('You have not rated any supplier yet.')" :description="__('Rate the hardware shops and factories you buy from. You can rate each one again every month.')" />
        @else
            <div class="boq-table-wrapper">
                <table class="boq-table">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Rating') }}</th><th>{{ __('Comment') }}</th><th>{{ __('Date') }}</th><th class="text-right"><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                    <tbody>
                        @foreach($myRatings as $mine)
                            <tr wire:key="mine-{{ $mine->id }}">
                                <td>
                                    <button type="button" wire:click="showSupplier({{ $mine->supplier_id }})" class="boq-table-title boq-table-link text-left">{{ $mine->supplier?->name }}</button>
                                    <div class="boq-table-subtitle">{{ $mine->supplier?->type === 'factory' ? __('Factory') : __('Hardware') }}</div>
                                </td>
                                <td><x-stars :value="$mine->rating" /></td>
                                <td class="max-w-md text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($mine->comment, 90) ?: '—' }}</td>
                                <td class="text-sm">{{ \App\Support\Format::date($mine->rated_at) }}</td>
                                <td class="text-right">
                                    @if($mine->supplier?->is_active)
                                        <button type="button" wire:click="openRate({{ $mine->supplier_id }})" class="boq-icon-btn" title="{{ __('Rate again') }}" aria-label="{{ __('Rate :name again', ['name' => $mine->supplier->name]) }}"><i class="fas fa-rotate"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Rate a supplier --}}
    @if($showRate)
        <div class="boq-modal-backdrop" wire:key="rate-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeRate()" role="dialog" aria-modal="true" aria-labelledby="rate-title">
            <form wire:submit="saveRating" class="boq-modal boq-modal-lg">
                <div class="boq-modal-head">
                    <h2 id="rate-title"><i class="fas fa-star"></i> {{ __('Rate a Supplier') }}</h2>
                    <button type="button" wire:click="closeRate" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="boq-modal-body space-y-4">
                    @if($rateSupplier)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                            <i class="fas {{ $rateSupplier->type === 'factory' ? 'fa-industry' : 'fa-store' }} text-brand-600" aria-hidden="true"></i>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-slate-800">{{ $rateSupplier->name }}</div>
                                <div class="text-xs text-slate-500">{{ collect([$rateSupplier->type === 'factory' ? __('Factory') : __('Hardware'), $rateSupplier->location])->filter()->implode(' · ') }}</div>
                            </div>
                            <button type="button" wire:click="clearSupplier" class="boq-btn-secondary">{{ __('Change') }}</button>
                        </div>
                    @else
                        <div>
                            <label for="rate-search" class="boq-field-label">{{ __('Hardware or factory') }} <span class="boq-field-required" aria-hidden="true">*</span></label>
                            <input id="rate-search" type="search" wire:model.live.debounce.300ms="rateSearch" class="boq-field" placeholder="{{ __('Search by name or location...') }}" autocomplete="off">
                            @error('rateSupplierId') <p class="boq-field-error">{{ $message }}</p> @enderror
                            <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                                @forelse($rateChoices as $choice)
                                    <li wire:key="choice-{{ $choice->id }}">
                                        <button type="button" wire:click="chooseSupplier({{ $choice->id }})" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-slate-50">
                                            <i class="fas {{ $choice->type === 'factory' ? 'fa-industry' : 'fa-store' }} text-slate-400" aria-hidden="true"></i>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-slate-800">{{ $choice->name }}</span>
                                                <span class="block truncate text-xs text-slate-500">{{ $choice->location ?: $choice->region ?: '—' }}</span>
                                            </span>
                                            @if($choice->ratings_count)<x-stars :value="$choice->rating" :count="$choice->ratings_count" />@endif
                                        </button>
                                    </li>
                                @empty
                                    <li class="px-3 py-3 text-sm text-slate-500">{{ __('No active hardware or factory matches your search.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif

                    @if($rateSupplier)
                        <div>
                            <span class="boq-field-label">{{ __('Overall rating') }} <span class="boq-field-required" aria-hidden="true">*</span></span>
                            <x-star-input model="rateForm.rating" :label="__('Overall rating')" wire:key="star-overall-{{ $rateSupplierId }}" />
                            @error('rateForm.rating') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="boq-form-grid">
                            @foreach($criteriaLabels as $field => $label)
                                <div>
                                    <span class="boq-field-label">{{ $label }}</span>
                                    <x-star-input :model="'rateForm.'.$field" :label="$label" optional size="md" wire:key="star-{{ $field }}-{{ $rateSupplierId }}" />
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <label for="rate-comment" class="boq-field-label">{{ __('Comment') }}</label>
                            <textarea id="rate-comment" wire:model="rateForm.comment" rows="3" maxlength="1000" class="boq-field boq-textarea" placeholder="{{ __('What was good, and what could be better? (prices, stock, delivery, service)') }}"></textarea>
                            @error('rateForm.comment') <p class="boq-field-error">{{ $message }}</p> @enderror
                        </div>
                        <p class="text-xs text-slate-500">{{ __('You can rate each supplier once a month; rating again in the same month updates your rating.') }}</p>
                    @endif
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeRate" class="boq-btn-secondary">{{ __('Cancel') }}</button>
                    <button type="submit" class="boq-btn-primary" wire:loading.attr="disabled" wire:target="saveRating" @disabled(! $rateSupplier)>
                        <i class="fas fa-floppy-disk" wire:loading.remove wire:target="saveRating"></i><i class="fas fa-spinner fa-spin" wire:loading wire:target="saveRating"></i> {{ __('Save Rating') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Supplier performance --}}
    @if($detail)
        <div class="boq-modal-backdrop" wire:key="supplier-detail-{{ $detail->id }}" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.closeSupplier()" role="dialog" aria-modal="true" aria-labelledby="detail-title">
            <div class="boq-modal boq-modal-xl">
                <div class="boq-modal-head">
                    <h2 id="detail-title"><i class="fas {{ $detail->type === 'factory' ? 'fa-industry' : 'fa-store' }}"></i> {{ $detail->name }}</h2>
                    <button type="button" wire:click="closeSupplier" class="boq-modal-close" aria-label="{{ __('Close') }}"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="boq-modal-body space-y-4">
                    <p class="text-sm text-slate-600">
                        {{ collect([$detail->type === 'factory' ? __('Factory') : __('Hardware'), $detail->location, $detail->region])->filter()->implode(' · ') }}
                        @if($detail->website_url) · <a href="{{ $detail->website_url }}" target="_blank" rel="noopener noreferrer" class="boq-table-link">{{ parse_url($detail->website_url, PHP_URL_HOST) ?: $detail->website_url }}</a>@endif
                    </p>

                    <div class="boq-stats-grid boq-stats-compact">
                        <x-stat-card :label="__('Overall rating')" :value="$detail->ratings_count ? number_format((float) $detail->rating, 1).' / 5' : '—'" :hint="trans_choice(':count user|:count users', $detail->ratings_count, ['count' => $detail->ratings_count])" icon="fa-star" color="amber" />
                        <x-stat-card :label="__('Rank').' · '.$periodLabel" :value="$detailRank ? '#'.$detailRank['rank'] : '—'" :hint="$detailRank ? __('of :count rated', ['count' => $detailRank['of']]) : __('Not rated in this period')" icon="fa-ranking-star" color="blue" />
                        <x-stat-card :label="__('Ratings').' · '.$periodLabel" :value="\App\Support\Format::number($detailSummary['total'], 0)" :hint="$detailSummary['average'] !== null ? __('Average :value', ['value' => number_format($detailSummary['average'], 1)]) : null" icon="fa-calendar-check" color="green" />
                        <x-stat-card :label="__('All-time ratings')" :value="\App\Support\Format::number($detailAllTime['total'], 0)" icon="fa-clock-rotate-left" color="purple" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <section class="boq-card">
                            <div class="boq-card-header"><h3 class="boq-card-title">{{ __('Scores by area') }}</h3><span class="boq-card-subtitle">{{ __('All time') }}</span></div>
                            <div class="boq-card-body">
                                <x-charts.bar :max="5" :label="__('Scores by area')" :empty="__('No detailed scores yet.')"
                                    :items="collect($detailAllTime['criteria'])->filter(fn ($v) => $v !== null)->map(fn ($v, $field) => ['label' => $criteriaLabels[$field], 'value' => $v, 'color' => '#2563eb'])->values()->all()" />
                            </div>
                        </section>
                        <section class="boq-card">
                            <div class="boq-card-header"><h3 class="boq-card-title">{{ __('Rating distribution') }}</h3><span class="boq-card-subtitle">{{ __('All time') }}</span></div>
                            <div class="boq-card-body">
                                <x-charts.pie :label="__('Rating distribution')"
                                    :slices="collect($detailAllTime['distribution'])->map(fn ($count, $stars) => ['label' => trans_choice(':count star|:count stars', $stars, ['count' => $stars]), 'value' => $count, 'color' => $starColors[$stars]])->values()->all()"
                                    :center="$detailAllTime['average'] !== null ? number_format($detailAllTime['average'], 1) : null" :center-label="__('average')" />
                            </div>
                        </section>
                        <section class="boq-card lg:col-span-2">
                            <div class="boq-card-header"><h3 class="boq-card-title">{{ __('Monthly trend') }}</h3><span class="boq-card-subtitle">{{ __('Last 12 months') }}</span></div>
                            <div class="boq-card-body"><x-charts.line :points="$detailAllTime['trend']" :label="__('Monthly trend')" :value-label="__('Average rating')" :count-label="__('Ratings')" /></div>
                        </section>
                    </div>

                    <section>
                        <h3 class="boq-section-title mb-2"><i class="fas fa-comments" aria-hidden="true"></i> {{ __('Recent reviews') }}</h3>
                        @forelse($detailReviews as $review)
                            <article wire:key="review-{{ $review->id }}" @class(['border-b border-slate-100 py-3', 'opacity-60' => $review->is_hidden])>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-stars :value="$review->rating" />
                                    <span class="text-xs text-slate-500">
                                        {{ $isAdmin ? ($review->user?->name ?? __('Deleted user')) : __('Verified user') }} · {{ \App\Support\Format::date($review->rated_at) }}
                                    </span>
                                    @if($review->is_hidden)<span class="boq-badge boq-badge-warning">{{ __('Hidden') }}</span>@endif
                                    @if($isAdmin)
                                        <button type="button" wire:click="toggleHidden({{ $review->id }})" class="boq-btn-secondary ml-auto text-xs">
                                            <i class="fas {{ $review->is_hidden ? 'fa-eye' : 'fa-eye-slash' }}"></i> {{ $review->is_hidden ? __('Show') : __('Hide') }}
                                        </button>
                                    @endif
                                </div>
                                @if($review->comment)<p class="mt-1 text-sm text-slate-700">{{ $review->comment }}</p>@endif
                            </article>
                        @empty
                            <p class="text-sm text-slate-500">{{ __('No reviews yet.') }}</p>
                        @endforelse
                    </section>
                </div>
                <div class="boq-modal-foot">
                    <button type="button" wire:click="closeSupplier" class="boq-btn-secondary">{{ __('Close') }}</button>
                    @if($detail->is_active)
                        <button type="button" wire:click="openRate({{ $detail->id }})" class="boq-btn-primary"><i class="fas fa-star"></i> {{ __('Rate') }}</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
