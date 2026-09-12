<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateMcpToken extends Command
{
    protected $signature = 'mcp:token {email : Service account email} {--abilities=* : MCP abilities to grant}';
    protected $description = 'Create a scoped Sanctum token for the BOQ MCP service account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Service account user not found.');
            return self::FAILURE;
        }

        $allowed = ['boq.read', 'boq.calculate', 'materials.read', 'prices.read', 'suppliers.read', 'reports.read'];
        $abilities = $this->option('abilities') ?: $allowed;
        $invalid = array_diff($abilities, $allowed);
        if ($invalid) {
            $this->error('Unsupported MCP abilities: '.implode(', ', $invalid));
            return self::FAILURE;
        }

        $this->line($user->createToken('boq-mcp', $abilities)->plainTextToken);
        $this->warn('Store this token only in BOQ_MCP_TOKEN. It will not be shown again.');
        return self::SUCCESS;
    }
}
