<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ExpenseService;
use App\Services\ReceiptExtractionService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function projects(Request $request)
    {
        abort_if($request->user()->organisation_id === null, 403);
        $projects = Project::where('organisation_id', $request->user()->organisation_id)
            ->whereHas('assignments', fn ($assignments) => $assignments
                ->where('user_id', $request->user()->id)->whereNull('deleted_at'))
            // The tenant/active-assignment predicates are the create policy's
            // complete access boundary; avoid repeating an exists query per row.
            ->orderBy('name')->paginate(100, ['id', 'name', 'currency']);

        return response()->json(['data' => $projects->items(), 'meta' => [
            'current_page' => $projects->currentPage(), 'last_page' => $projects->lastPage(),
        ]]);
    }

    public function index(Request $request)
    {
        $query = Expense::visibleTo($request->user());
        $expenses = $query->latest('purchase_date')->paginate(min(max((int) $request->integer('per_page', 15), 1), 100));
        $expenses->getCollection()->each(fn (Expense $expense) => $this->authorize('view', $expense));

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request)
    {
        $expense = app(ExpenseService::class)->create($request->user(), $request->validated());

        return (new ExpenseResource($expense))->response()->setStatusCode(201);
    }

    /**
     * Read expense fields out of a receipt image or PDF for review, without saving
     * anything. The caller shows the returned fields and later creates the expense
     * and attaches the receipt through the normal endpoints.
     */
    public function extract(ExtractExpenseRequest $request)
    {
        $data = app(ReceiptExtractionService::class)->extract(
            $request->file('file'),
            $request->user()->organisation_id
        );

        return response()->json(['data' => $data]);
    }

    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense->load(['receipts', 'items']));
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);
        $expense->delete();

        return response()->noContent();
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        app(ExpenseService::class)->update($request->user(), $expense, $request->validated());

        return new ExpenseResource($expense->refresh()->load(['receipts', 'items']));
    }
}
