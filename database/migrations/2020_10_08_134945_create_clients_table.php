<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('document')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('address');
            $table->string('number')->nullable();
            $table->string('village');
            $table->string('zipcode');
            $table->string('city');
            $table->string('state');
            $table->string('cnae');
            $table->string('textcnae');
            $table->string('operator')->nullable();
            $table->boolean('active')->default(1);
            $table->string('classification')->nullable();
            $table->string('number_client')->nullable();
            $table->string('password_client')->nullable();
            $table->integer('user');
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
        Schema::dropIfExists('clients');
    }
}
