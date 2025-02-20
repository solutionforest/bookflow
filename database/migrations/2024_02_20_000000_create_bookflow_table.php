<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookflow_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('unit'); // fixed, hour, day
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->json('days_of_week')->nullable();
            $table->integer('minimum_units')->default(1);
            $table->integer('maximum_units')->nullable();
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('service_type')->nullable();
            $table->timestamps();
        });

        Schema::create('bookflow_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('bookable_type');
            $table->unsignedBigInteger('bookable_id');
            $table->string('customer_type');
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('rate_id')->nullable()->constrained('bookflow_rates')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['bookable_type', 'bookable_id']);
            $table->index(['customer_type', 'customer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookflow_bookings');
        Schema::dropIfExists('bookflow_rates');
    }
};
