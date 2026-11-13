<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The interface language each person chose (null: follow the browser).
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('email');
        });

        // The language of a site's public accessibility statement and audit reports.
        Schema::table('sites', function (Blueprint $table) {
            $table->string('language', 10)->default('en')->after('scan_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn('language'));
    }
};
