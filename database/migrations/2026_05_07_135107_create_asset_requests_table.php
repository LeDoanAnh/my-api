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
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('borrower_id')->constrained('users');
            $table->foreignId('handler_id')->nullable()->constrained('users');
            $table->timestamp('expected_borrow_date');
            $table->timestamp('borrow_date')->nullable();;
            $table->timestamp('expected_return_date')->nullable();
            $table->timestamp('actual_return_date')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_asset_requests');
    }
};
