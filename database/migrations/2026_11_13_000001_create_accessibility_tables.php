<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('sitemap_url')->nullable();
            $table->unsignedSmallInteger('max_pages')->default(50);
            $table->string('scan_frequency')->default('weekly'); // manual | daily | weekly
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 40);
            $table->string('title')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url_hash']);
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued | running | completed | failed
            $table->string('trigger')->default('manual'); // manual | scheduled
            $table->unsignedInteger('pages_total')->default(0);
            $table->unsignedInteger('pages_scanned')->default(0);
            $table->unsignedInteger('pages_failed')->default(0);
            $table->unsignedInteger('issues_open')->default(0);
            $table->unsignedInteger('issues_new')->default(0);
            $table->unsignedInteger('issues_fixed')->default(0);
            $table->unsignedInteger('critical')->default(0);
            $table->unsignedInteger('serious')->default(0);
            $table->unsignedInteger('moderate')->default(0);
            $table->unsignedInteger('minor')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });

        Schema::create('scan_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // ok | failed
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('violations')->default(0);
            $table->unsignedInteger('passes')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['scan_id', 'page_id']);
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 40);
            $table->string('rule_id');
            $table->string('impact')->nullable(); // critical | serious | moderate | minor
            $table->json('wcag');
            $table->string('help');
            $table->text('description')->nullable();
            $table->string('help_url')->nullable();
            $table->text('target');
            $table->text('html')->nullable();
            $table->text('failure_summary')->nullable();
            $table->string('status')->default('open'); // open | fixed
            $table->foreignId('first_seen_scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->foreignId('last_seen_scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->foreignId('fixed_in_scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'fingerprint']);
            $table->index(['site_id', 'status', 'impact']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
        Schema::dropIfExists('scan_pages');
        Schema::dropIfExists('scans');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('sites');
    }
};
