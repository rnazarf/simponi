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
        Schema::table('kuis', function (Blueprint $table) {
            $table->boolean('anti_curang')->default(true)->after('tampilkan_nilai');
            $table->unsignedTinyInteger('maks_pelanggaran')->default(0)->after('anti_curang'); // 0 = pantau saja, >0 batas auto-submit
        });

        Schema::table('percobaan', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pelanggaran')->default(0)->after('nilai');
            $table->json('pelanggaran')->nullable()->after('jumlah_pelanggaran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('percobaan', function (Blueprint $table) {
            $table->dropColumn(['jumlah_pelanggaran', 'pelanggaran']);
        });

        Schema::table('kuis', function (Blueprint $table) {
            $table->dropColumn(['anti_curang', 'maks_pelanggaran']);
        });
    }
};
