<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('feedback_jobs', function (Blueprint $table) {
            $table->dropColumn(['desired_salary']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('feedback_jobs', function (Blueprint $table) {
            $table->string('desired_salary', 255)->after('type')->nullable();
        });
    }
};
