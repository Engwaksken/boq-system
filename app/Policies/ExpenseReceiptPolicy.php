<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\ExpenseReceipt;
use App\Models\User;

class ExpenseReceiptPolicy
{
    public function view(User $user, ExpenseReceipt $receipt): bool
    {
        $expense = $receipt->expense;

        return $expense !== null && app(ExpensePolicy::class)->view($user, $expense);
    }

    public function create(User $user, Expense $expense): bool
    {
        return app(ExpensePolicy::class)->view($user, $expense);
    }

    public function delete(User $user, ExpenseReceipt $receipt): bool
    {
        $expense = $receipt->expense;

        return $expense !== null && app(ExpensePolicy::class)->delete($user, $expense);
    }
}
