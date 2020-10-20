<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpportunitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->date('forecast')->nullable();
            $table->boolean('active')->default(1);
            $table->integer('client')->nullable();
            $table->string('funnel')->default('Prospect');
            $table->string('status')->default('Oportunidade');
            $table->decimal('recipe')->nullable();
            $table->string('lines')->nullable();
            $table->boolean('archive')->default(0);
            $table->date('activate')->nullable();
            $table->boolean('renew')->default(0);
            $table->date('renew_date')->nullable();
            $table->date('activity_date')->nullable();
            $table->string('activity_status')->default(0);
            $table->string('order_status')->default('Validação Pendente');
            $table->string('offer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('opportunities');
    }
}
