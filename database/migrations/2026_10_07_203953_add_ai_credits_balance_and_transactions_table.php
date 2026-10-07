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
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedInteger('ai_credits_balance')->default(50)->after('plan');
        });

        Schema::create('ai_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('type'); // usage, topup, bonus, adjustment
            $table->string('description')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('cost_usd', 8, 5)->default(0);
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_credit_transactions');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('ai_credits_balance');
        });
    }
};
