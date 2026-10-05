<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        // Backfill existing user-business relationships
        $users = DB::table('users')->whereNotNull('business_id')->get(['id', 'business_id', 'role']);
        $now = now();
        $records = [];
        foreach ($users as $u) {
            $records[] = [
                'business_id' => $u->business_id,
                'user_id' => $u->id,
                'role' => $u->role ?? 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($records)) {
            DB::table('business_user')->insertOrIgnore($records);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
