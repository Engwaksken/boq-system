<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_pages_load_the_custom_confirm_dialog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('faqs.index'))
            ->assertOk()
            ->assertSee('boq-confirm', false)
            ->assertSee('Please confirm');
    }
}
