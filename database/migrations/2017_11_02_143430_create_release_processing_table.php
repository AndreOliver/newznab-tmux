<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseProcessingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_processing', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->primary()->comment('FK to releases.id');
            $table->boolean('proc_pp')->default(0);
            $table->boolean('proc_sorter')->default(0);
            $table->boolean('proc_par2')->default(0);
            $table->boolean('proc_nfo')->default(0);
            $table->boolean('proc_files')->default(0);
            $table->boolean('proc_uid')->default(0);
            $table->boolean('proc_srr')->default(0)->comment('Has the release been srr
processed');
            $table->boolean('proc_hash16k')->default(0)->comment('Has the release been
hash16k processed');
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
        Schema::dropIfExists('release_processing');
    }
}
