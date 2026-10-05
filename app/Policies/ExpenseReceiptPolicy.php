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

    /** Receipt extraction must remain scoped to users who can view its expense. */
    public function extract(User $user, ExpenseReceipt $receipt): bool
    {
        return $this->view($user, $receipt);
    }

    /** Reviewing extracted receipt data is an expense update operation. */
    public function review(User $user, ExpenseReceipt $receipt): bool
    {
        $expense = $receipt->expense;

        return $expense !== null && app(ExpensePolicy::class)->update($user, $expense);
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
