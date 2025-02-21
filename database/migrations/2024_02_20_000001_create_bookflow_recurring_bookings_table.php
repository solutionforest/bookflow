<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookflow_recurring_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('bookable_type');
            $table->unsignedBigInteger('bookable_id');
            $table->string('customer_type');
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('rate_id')->nullable()->constrained('bookflow_rates')->nullOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->json('days_of_week');
            $table->dateTime('starts_from');
            $table->dateTime('ends_at')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bookable_type', 'bookable_id']);
            $table->index(['customer_type', 'customer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookflow_recurring_bookings');
    }
};
