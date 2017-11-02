<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseRelatedIdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_related_ids', function (Blueprint $table) {
            $table->integer('releases_id')->unsigned()->primary()->comment('FK to releases.id');
            $table->integer('videos_id')->unsigned()->default(0)->index('ix_releases_videos_id')->comment('FK to videos.id of the parent series.');
            $table->integer('tv_episodes_id')->default(0)->index('ix_releases_tv_episodes_id')->comment('FK to tv_episodes.id for the episode.');
            $table->integer('imdbid')->unsigned()->nullable()->default(null)->index('ix_releases_imdbid');
            $table->integer('xxxinfo_id')->default(0)->index('ix_releases_xxxinfo_id');
            $table->integer('musicinfo_id')->nullable()->default(null)->comment('FK to musicinfo.id');
            $table->integer('consoleinfo_id')->nullable()->default(null)->index('ix_releases_consoleinfo_id')->comment('FK to consoleinfo.id');
            $table->integer('gamesinfo_id')->default(0)->index('ix_releases_gamesinfo_id');
            $table->integer('bookinfo_id')->nullable()->default(null)->index('ix_releases_bookinfo_id')->comment('FK to bookinfo.id');
            $table->integer('anidbid')->nullable()->default(null)->index('ix_releases_anidbid')->comment('FK to anidb_titles.anidbid');
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
        Schema::dropIfExists('release_related_ids');
    }
}
