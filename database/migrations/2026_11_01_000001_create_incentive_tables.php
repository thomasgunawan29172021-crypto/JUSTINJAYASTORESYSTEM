<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->string('currency', 3);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('approval_status', 20)->default('pending');
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->foreignId('payment_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_checked_at')->nullable();
            $table->boolean('is_cooperation')->default(false);
            $table->decimal('total_capital', 18, 2)->default(0);
            $table->decimal('total_sales', 18, 2)->default(0);
            $table->decimal('total_profit', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['transaction_date', 'branch_id', 'currency']);
            $table->index(['approval_status', 'payment_status']);
        });

        Schema::create('incentive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_submission_id')->constrained()->cascadeOnDelete();
            $table->string('product_name', 150);
            $table->string('ram', 50)->nullable();
            $table->string('color', 80)->nullable();
            $table->string('condition', 20);
            $table->string('imei', 100)->nullable();
            $table->decimal('capital_price', 18, 2);
            $table->decimal('sale_price', 18, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('incentive_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('incentive_type', 20);
            $table->string('cooperation_role', 20)->nullable();
            $table->decimal('share_percent', 5, 2);
            $table->timestamps();
            $table->unique(['incentive_submission_id', 'user_id']);
        });

        Schema::create('incentive_social_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incentive_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained('users')->restrictOnDelete();
            $table->string('platform', 30);
            $table->string('account_name', 150);
            $table->timestamps();
        });

        Schema::create('incentive_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->string('type', 20);
            $table->string('label', 80)->nullable();
            $table->decimal('min_target', 18, 2);
            $table->decimal('reward_value', 12, 4);
            $table->timestamps();
            $table->unique(['branch_id', 'currency', 'type', 'min_target'], 'incentive_tier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_tiers');
        Schema::dropIfExists('incentive_social_actions');
        Schema::dropIfExists('incentive_participants');
        Schema::dropIfExists('incentive_items');
        Schema::dropIfExists('incentive_submissions');
    }
};
