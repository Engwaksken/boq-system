<?php

namespace Tests\Feature;

use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ExpenseBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_combines_repeated_lines_and_existing_spend_without_double_counting(): void
    {
        $project = Project::factory()->create();
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $project->organisation_id]);
        $item = BoqItem::factory()->create(['boq_id' => $boq->id, 'currency' => 'UGX', 'unit' => 'bags', 'quantity' => 10, 'approved_rate' => 10]);
        $expense = Expense::factory()->create(['project_id' => $project->id, 'organisation_id' => $project->organisation_id,
            'boq_item_id' => $item->id, 'currency' => 'UGX', 'total' => 60]);
        $expense->items()->create(['boq_item_id' => $item->id, 'boq_id' => $boq->id, 'description' => 'Cement', 'quantity' => 6, 'unit' => 'bags', 'rate' => 10, 'total' => 60]);
        Expense::factory()->create(['project_id' => $project->id, 'organisation_id' => $project->organisation_id,
            'boq_item_id' => $item->id, 'currency' => 'USD', 'total' => 1000]);
        $lines = array_fill(0, 2, ['boq_item_id' => $item->id, 'quantity' => 3, 'rate' => 10, 'unit' => 'bags']);
        $result = app(ExpenseBudgetService::class)->compare($project, $lines, 'UGX');
        $this->assertSame(60.0, $result[0]['spent']);
        $this->assertSame(120.0, $result[0]['projected']);
        $this->assertSame(-20.0, $result[0]['remaining']);
        $this->assertSame('over_budget', $result[1]['status']);
        $this->assertSame(120.0, $result[1]['progress']);
        $this->assertSame(0.0, app(ExpenseBudgetService::class)->compare($project, $lines, 'UGX', $expense->id)[0]['spent']);
        $this->assertSame('currency_mismatch', app(ExpenseBudgetService::class)->compare($project, $lines, 'USD')[0]['status']);
        $this->assertSame('unlinked', app(ExpenseBudgetService::class)->compare(Project::factory()->create(), $lines, 'UGX')[0]['status']);
    }
}
