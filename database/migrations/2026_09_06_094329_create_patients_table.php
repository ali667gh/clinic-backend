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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name'); // اضافه شدن نام کامل
            $table->string('first_name')->nullable(); // نالبل کردن برای جلوگیری از ارور
            $table->string('last_name')->nullable();  // نالبل کردن برای جلوگیری از ارور
            $table->string('file_number', 50)->unique()->nullable();
            $table->string('national_code')->unique()->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->text('medical_history')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
