<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('customers', function(Blueprint $t){$t->id();$t->string('name',100);$t->string('address',255)->nullable();$t->string('source',50)->nullable();$t->foreignId('branch_id')->constrained();$t->foreignId('created_by')->constrained('users');$t->text('notes')->nullable();$t->timestamps();$t->softDeletes();$t->index('name');});
  Schema::create('customer_contacts', function(Blueprint $t){$t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->string('type',20);$t->string('value',150);$t->boolean('is_primary')->default(false);$t->timestamps();$t->index(['type','value']);});
  Schema::create('customer_histories', function(Blueprint $t){$t->id();$t->foreignId('customer_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->nullable()->constrained();$t->string('action',20);$t->json('changes')->nullable();$t->text('note')->nullable();$t->timestamp('created_at');});
 }
 public function down(): void {Schema::dropIfExists('customer_histories');Schema::dropIfExists('customer_contacts');Schema::dropIfExists('customers');}
};
