<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FlashAutohideTest extends TestCase
{
    public function test_success_messages_fade_out_and_errors_stay(): void
    {
        session()->flash('status', 'Project saved.');
        session()->flash('error', 'Something failed.');

        $html = Blade::render('<x-ui.flash />');

        $this->assertMatchesRegularExpression('/data-autohide="5000"[^>]*>\s*<i class="fas fa-circle-check/', $html);
        $this->assertStringContainsString('Project saved.', $html);
        $this->assertSame(1, substr_count($html, 'data-autohide='), 'Only the success message fades; the error stays.');
    }
}
