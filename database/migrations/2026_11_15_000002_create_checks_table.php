<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A check scans a preview or staging copy of a site (for example a pull request
     * deployment) and compares it with the site's known issues, without changing them.
     */
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued'); // queued | running | completed | failed
            $table->string('base_url');
            $table->string('ref')->nullable(); // e.g. a branch, commit or pull request
            $table->json('paths');
            $table->unsignedInteger('pages_scanned')->default(0);
            $table->unsignedInteger('pages_failed')->default(0);
            $table->unsignedInteger('issues_new')->default(0);
            $table->unsignedInteger('issues_existing')->default(0);
            $table->unsignedInteger('issues_fixed')->default(0);
            $table->json('new_by_impact')->nullable();
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
