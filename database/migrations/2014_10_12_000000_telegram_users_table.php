<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable(); 
            $table->string('last_name')->nullable();  
            $table->string('username')->nullable();   
            $table->string('trello_access_token')->nullable()->unique(); 
            $table->bigInteger('chat_id')->unique();
            $table->tinyInteger('pm')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
