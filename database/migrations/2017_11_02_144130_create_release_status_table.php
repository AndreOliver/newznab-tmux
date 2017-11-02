<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_status', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->primary()->comment('FK to releases.id');
            $table->boolean('nfostatus')->default(0);
            $table->boolean('jpgstatus')->default(0);
            $table->boolean('videostatus')->default(0);
            $table->boolean('audiostatus')->default(0);
            $table->boolean('dehashstatus')->default(0);
            $table->boolean('reqidstatus')->default(0);
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
        Schema::dropIfExists('release_status');
    }
}
