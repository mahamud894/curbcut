<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A workspace: a company, or one client of an agency.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('personal')->default(false);
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // owner | admin | member
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        // Give every existing user a personal workspace and move their sites into it.
        // People who were added to individual sites keep that access as site guests.
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $orgId = DB::table('organizations')->insertGetId([
                'name' => "{$user->name}'s workspace",
                'personal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('organization_user')->insert(['organization_id' => $orgId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $user->id)->update(['current_organization_id' => $orgId]);
            DB::table('sites')->where('user_id', $user->id)->update(['organization_id' => $orgId]);
        }
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_organization_id'));
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
