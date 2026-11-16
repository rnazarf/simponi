<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuan', function (Blueprint $t) {
            $t->enum('jenis_presensi', ['daring', 'tatap_muka', 'tidak_ada'])->default('daring')->after('tanggal');
        });

        // Pertemuan lama yang tidak punya presensi_buka diset ke 'tidak_ada'
        DB::table('pertemuan')->whereNull('presensi_buka')->update(['jenis_presensi' => 'tidak_ada']);
    }

    public function down(): void
    {
        Schema::table('pertemuan', function (Blueprint $t) {
            $t->dropColumn('jenis_presensi');
        });
    }
};
