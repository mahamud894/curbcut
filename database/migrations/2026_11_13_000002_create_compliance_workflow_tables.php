<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who work on a site besides its owner.
        Schema::create('site_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'user_id']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable()->after('assignee_id');
            $table->string('dismissed_reason')->nullable()->after('due_on'); // false_positive | wont_fix | not_applicable
            $table->text('dismissed_note')->nullable()->after('dismissed_reason');
            $table->foreignId('dismissed_by')->nullable()->after('dismissed_note')->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable()->after('dismissed_by');

            $table->index(['site_id', 'assignee_id']);
        });

        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        // Manual testing results for each WCAG success criterion.
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 10); // e.g. "1.4.3"
            $table->string('status')->default('not_tested'); // not_tested | pass | fail | not_applicable
            $table->text('notes')->nullable();
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'criterion']);
        });

        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('organisation')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('conformance_override')->nullable(); // full | partial | none
            $table->text('additional_limitations')->nullable();
            $table->text('feedback_process')->nullable();
            $table->text('enforcement_procedure')->nullable();
            $table->string('public_token', 40)->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Append-only audit trail of who changed what.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('action');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('statements');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('issue_comments');
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
            $table->dropConstrainedForeignId('dismissed_by');
            $table->dropColumn(['due_on', 'dismissed_reason', 'dismissed_note', 'dismissed_at']);
        });
        Schema::dropIfExists('site_user');
    }
};
