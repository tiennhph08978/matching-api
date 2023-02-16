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
        Schema::table('application_user_licenses_qualifications', function (Blueprint $table) {
            $table->dropColumn('job_posting_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_user_licenses_qualifications', function (Blueprint $table) {
            $table->unsignedBigInteger('job_posting_id')->after('user_id');
        });
    }
};
