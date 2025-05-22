<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('versions', function (Blueprint $table) {
            $uuid = config('versionable.uuid');

            $uuid ? $table->uuid('id')->primary() : $table->bigIncrements('id');
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();

            $uuid ? $table->uuidMorphs('versionable') : $table->morphs('versionable');
            $table->json('contents')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('versions');
    }
};
