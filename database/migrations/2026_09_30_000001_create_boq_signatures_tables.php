<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signatures on BOQs: the preparer's and the client's sign-off, and the
 * one-time links sent to clients so they can sign remotely.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boq_signatures')) {
            Schema::create('boq_signatures', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
                $table->string('role', 20); // preparer | client
                $table->string('name');
                $table->string('title')->nullable();
                $table->date('signed_at')->nullable();
                $table->string('image_path');
                $table->string('method', 20)->default('drawn'); // drawn | uploaded
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('signature_request_id')->nullable();
                $table->timestamps();

                $table->unique(['boq_id', 'role']);
            });
        }

        if (! Schema::hasTable('signature_requests')) {
            Schema::create('signature_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boq_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->text('url')->nullable(); // encrypted, so the owner can copy the link again
                $table->string('email')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['boq_id', 'used_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
        Schema::dropIfExists('boq_signatures');
    }
};
