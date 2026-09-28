<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each user's business identity, used to brand the BOQs they own.
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('logo_path')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('tin', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('physical_address', 500)->nullable();
            $table->string('postal_address', 255)->nullable();
            $table->string('telephone', 40)->nullable();
            $table->string('alt_telephone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('boqs', function (Blueprint $table) {
            // Owner = the user whose company identity brands this BOQ; never the viewer.
            $table->foreignId('owner_id')->nullable()->after('organisation_id')->constrained('users')->nullOnDelete();
            $table->string('reference', 40)->nullable()->unique()->after('code');
            // Company details as they were when the BOQ was first exported.
            $table->json('company_snapshot')->nullable()->after('metadata');
        });

        // Existing BOQs belong to the user who owns their project.
        DB::table('boqs')
            ->whereNull('owner_id')
            ->update(['owner_id' => DB::raw('(select user_id from projects where projects.id = boqs.project_id)')]);
    }

    public function down(): void
    {
        Schema::table('boqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['reference', 'company_snapshot']);
        });
        Schema::dropIfExists('company_profiles');
    }
};
