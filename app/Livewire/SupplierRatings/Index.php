<?php

namespace App\Livewire\SupplierRatings;

use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Services\SupplierRatings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Top rated hardware suppliers and factories (week, month, year, all time),
 * performance charts, and rating a supplier.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    #[Url(except: 'month')]
    public string $period = 'month';

    // Rate a supplier
    public bool $showRate = false;
    public ?int $rateSupplierId = null;
    public string $rateSearch = '';
    public array $rateForm = [];

    // Supplier details
    #[Url(as: 'supplier', except: null)]
    public ?int $detailId = null;

    /** Livewire update requests skip route middleware, so re-check on every request. */
    public function boot(): void
    {
        abort_unless(auth()->user()?->hasPermission('hardware-prices.view') || auth()->user()?->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->resetRateForm();
        if (! in_array($this->period, SupplierRatings::PERIODS, true)) {
            $this->period = 'month';
        }
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, SupplierRatings::PERIODS, true) ? $period : 'month';
    }

    // ------------------------------------------------------------------ rate

    public function openRate(?int $supplierId = null): void
    {
        $this->resetRateForm();
        $this->resetValidation();
        $this->rateSearch = '';
        $this->rateSupplierId = null;
        $this->detailId = null;
        $this->showRate = true;

        if ($supplierId) {
            $this->chooseSupplier($supplierId);
        }
    }

    public function closeRate(): void
    {
        $this->showRate = false;
        $this->rateSupplierId = null;
    }

    public function chooseSupplier(int $id): void
    {
        $supplier = Supplier::where('is_active', true)->findOrFail($id);
        $this->rateSupplierId = $supplier->id;
        $this->resetErrorBag('rateSupplierId');

        // Re-rating this month edits the existing rating.
        $current = app(SupplierRatings::class)->current($supplier, auth()->user());
        $this->resetRateForm();
        if ($current) {
            $this->rateForm = [
                'rating' => $current->rating,
                'comment' => (string) $current->comment,
            ] + collect(SupplierRating::CRITERIA)->keys()->mapWithKeys(fn ($f) => [$f => $current->{$f}])->all();
        }
    }

    public function clearSupplier(): void
    {
        $this->rateSupplierId = null;
    }

    public function saveRating(SupplierRatings $ratings): void
    {
        $star = ['nullable', 'integer', 'min:1', 'max:5'];
        $this->validate([
            'rateSupplierId' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'rateForm.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'rateForm.price_rating' => $star,
            'rateForm.quality_rating' => $star,
            'rateForm.delivery_rating' => $star,
            'rateForm.service_rating' => $star,
            'rateForm.comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rateSupplierId.required' => __('Choose the hardware or factory to rate.'),
            'rateForm.rating.required' => __('Choose an overall rating from 1 to 5 stars.'),
        ]);

        $supplier = Supplier::findOrFail($this->rateSupplierId);
        $ratings->rate($supplier, auth()->user(), $this->rateForm);

        $this->showRate = false;
        session()->flash('message', __('Thank you. Your rating of :name was saved.', ['name' => $supplier->name]));
    }

    // ------------------------------------------------------------------ details

    public function showSupplier(int $id): void
    {
        $this->detailId = Supplier::whereKey($id)->exists() ? $id : null;
    }

    public function closeSupplier(): void
    {
        $this->detailId = null;
    }

    /** Admins hide abusive or fake ratings; hidden ones do not count. */
    public function toggleHidden(int $ratingId, SupplierRatings $ratings): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $rating = SupplierRating::findOrFail($ratingId);
        $ratings->setHidden($rating, ! $rating->is_hidden);
    }

    // ------------------------------------------------------------------ helpers

    private function resetRateForm(): void
    {
        $this->rateForm = ['rating' => null, 'price_rating' => null, 'quality_rating' => null, 'delivery_rating' => null, 'service_rating' => null, 'comment' => ''];
    }

    public function render(SupplierRatings $ratings)
    {
        $user = auth()->user();
        $isAdmin = $user->isSuperAdmin();
        $search = trim($this->rateSearch);

        $detail = $this->detailId ? Supplier::find($this->detailId) : null;

        return view('livewire.supplier-ratings.index', [
            'isAdmin' => $isAdmin,
            'periodLabel' => SupplierRatings::periodLabel($this->period),
            'topHardware' => $ratings->leaderboard($this->period, Supplier::TYPE_SUPPLIER),
            'topFactories' => $ratings->leaderboard($this->period, Supplier::TYPE_FACTORY),
            // Admins see who needs to improve.
            'lowHardware' => $isAdmin ? $ratings->leaderboard($this->period, Supplier::TYPE_SUPPLIER, 5, lowest: true) : [],
            'lowFactories' => $isAdmin ? $ratings->leaderboard($this->period, Supplier::TYPE_FACTORY, 5, lowest: true) : [],
            'summary' => $ratings->summary($this->period),
            'hardwareTrend' => $ratings->trend(type: Supplier::TYPE_SUPPLIER),
            'factoryTrend' => $ratings->trend(type: Supplier::TYPE_FACTORY),
            'myRatings' => SupplierRating::with('supplier:id,name,type,location')
                ->where('user_id', $user->id)->orderByDesc('rated_at')->limit(8)->get(),
            'rateChoices' => $this->showRate && ! $this->rateSupplierId
                ? Supplier::where('is_active', true)
                    ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")->orWhere('region', 'like', "%{$search}%")))
                    ->orderByDesc('ratings_count')->orderBy('name')->limit(8)->get()
                : collect(),
            'rateSupplier' => $this->rateSupplierId ? Supplier::find($this->rateSupplierId) : null,
            'detail' => $detail,
            'detailSummary' => $detail ? $ratings->summary($this->period, $detail) : null,
            'detailAllTime' => $detail ? $ratings->summary('all', $detail) : null,
            'detailRank' => $detail ? $ratings->rankOf($detail, $this->period) : null,
            'detailReviews' => $detail ? $ratings->recentReviews($detail, 10, $isAdmin) : collect(),
        ]);
    }
}
