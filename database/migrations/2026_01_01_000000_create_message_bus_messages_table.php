<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_bus_messages', function (Blueprint $table) {
            $table->id();
            $table->longText('body');
            $table->text('headers')->nullable();
            $table->string('queue_name', 190)->default('default');
            $table->dateTime('created_at');
            $table->dateTime('available_at');
            $table->dateTime('delivered_at')->nullable();

            $table->index(['queue_name', 'available_at', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_bus_messages');
    }
};
