<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_pricing_batches', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('boq_id')->constrained()->nullOnDelete();
            $table->string('operation')->default('generation')->after('organisation_id');
            $table->string('job_batch_id')->nullable()->after('operation')->index();
            $table->string('provider')->nullable()->after('job_batch_id');
            $table->string('current_stage')->nullable()->after('provider');
            $table->string('message')->nullable()->after('current_stage');
            $table->text('error_message')->nullable()->after('message');
            $table->timestamp('started_at')->nullable()->after('error_message');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->string('location')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('boq_pricing_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['operation', 'job_batch_id', 'provider', 'current_stage', 'message', 'error_message', 'started_at', 'completed_at', 'cancelled_at']);
        });
    }
};
