<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('cash_shift_id')->nullable()->after('user_id')->constrained('cash_shifts')->nullOnDelete();
            $table->index('cash_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) { $table->dropConstrainedForeignId('cash_shift_id'); });
        Schema::dropIfExists('cash_shifts');
    }
};
