<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penjadwalan mata pelajaran (timetabling).
 *
 * Diubah : users (+kode_guru), jadwal (+versi_jadwal_id, guru_id, jam_ke_mulai, jam_ke_selesai, dikunci).
 * Baru   : slot_waktu, beban_ajar, ketersediaan_guru, versi_jadwal.
 * Tidak diubah: tahun_ajaran, jurusan, kelas, mapel, ruang dan seluruh tabel pembelajaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kode singkat guru seperti di jadwal kertas (SWP, MONA, ...). Username tetap NIP.
        Schema::table('users', function (Blueprint $t) {
            $t->string('kode_guru', 15)->nullable()->unique()->after('username');
        });

        // Struktur jam per hari. Senin, Jumat, dan hari lain boleh berbeda (upacara, senam, istirahat).
        Schema::create('slot_waktu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $t->unsignedTinyInteger('hari'); // 1 = Senin ... 6 = Sabtu
            $t->unsignedTinyInteger('jam_ke')->nullable(); // null untuk baris istirahat
            $t->time('jam_mulai');
            $t->time('jam_selesai');
            $t->enum('jenis', ['kbm', 'istirahat', 'upacara', 'senam'])->default('kbm');
            $t->string('keterangan')->nullable();
            $t->timestamps();
            $t->unique(['tahun_ajaran_id', 'hari', 'jam_mulai']);
        });

        // Beban mengajar: satu ruang (kelas + mapel) boleh diampu lebih dari satu guru
        // (contoh: Das. AT di X ATP A diajar 4 guru). ruang.guru_id tetap menjadi pengampu utama di LMS.
        Schema::create('beban_ajar', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ruang_id')->constrained('ruang')->cascadeOnDelete();
            $t->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('jam_per_minggu');
            $t->json('pola_blok')->nullable(); // contoh [4, 2] = satu blok 4 jam + satu blok 2 jam
            $t->timestamps();
            $t->unique(['ruang_id', 'guru_id']);
        });

        // Waktu yang tidak bisa (hard) atau sebaiknya dihindari (soft) oleh guru
        Schema::create('ketersediaan_guru', function (Blueprint $t) {
            $t->id();
            $t->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('slot_waktu_id')->constrained('slot_waktu')->cascadeOnDelete();
            $t->enum('status', ['tidak_bisa', 'hindari'])->default('tidak_bisa');
            $t->string('keterangan')->nullable();
            $t->timestamps();
            $t->unique(['guru_id', 'slot_waktu_id']);
        });

        // Satu tahun ajaran boleh punya beberapa versi jadwal (draf hasil solver, jadwal aktif, arsip).
        // Aturan aplikasi: hanya satu versi berstatus 'aktif' per tahun ajaran.
        Schema::create('versi_jadwal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $t->string('nama', 120);
            $t->enum('status', ['draf', 'aktif', 'arsip'])->default('draf');
            $t->enum('sumber', ['manual', 'solver', 'impor'])->default('manual');
            $t->enum('status_solver', ['menunggu', 'berjalan', 'selesai', 'gagal'])->nullable();
            $t->json('hasil_solver')->nullable(); // ringkasan: status solver, lama hitung, jumlah pelanggaran soft constraint
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Satu baris jadwal = satu blok pelajaran berurutan (contoh: Rabu jam 1-4).
        // jam_mulai/jam_selesai tetap diisi (turunan dari slot_waktu) supaya kode LMS yang sudah ada tidak berubah.
        Schema::table('jadwal', function (Blueprint $t) {
            $t->foreignId('versi_jadwal_id')->nullable()->after('id')->constrained('versi_jadwal')->cascadeOnDelete();
            $t->foreignId('guru_id')->nullable()->after('ruang_id')->constrained('users')->nullOnDelete(); // null = pakai ruang.guru_id
            $t->unsignedTinyInteger('jam_ke_mulai')->nullable()->after('hari');
            $t->unsignedTinyInteger('jam_ke_selesai')->nullable()->after('jam_ke_mulai');
            $t->boolean('dikunci')->default(false); // true = tidak diubah saat solver dijalankan ulang
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $t) {
            $t->dropConstrainedForeignId('versi_jadwal_id');
            $t->dropConstrainedForeignId('guru_id');
            $t->dropColumn(['jam_ke_mulai', 'jam_ke_selesai', 'dikunci']);
        });
        foreach (['ketersediaan_guru', 'versi_jadwal', 'beban_ajar', 'slot_waktu'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['kode_guru']);
            $t->dropColumn('kode_guru');
        });
    }
};
