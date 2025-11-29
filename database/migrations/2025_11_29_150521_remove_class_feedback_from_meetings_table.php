<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Meetings', function (Blueprint $table) {
            $table->dropColumn('class_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Meetings', function (Blueprint $table) {
            $table->text('class_feedback')->nullable()->after('summary')->comment('Ý kiến đóng góp tổng hợp của lớp');
        });
    }
};
