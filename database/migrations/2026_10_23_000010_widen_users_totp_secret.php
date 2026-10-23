<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TOTP secret disimpan terenkripsi (cast `encrypted`), sehingga ciphertext
     * melebihi VARCHAR(255) milik PRD §6.1 tabel users. Lebar kolom dinaikkan
     * tanpa mengubah tipe data atau menghapus kolom.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY totp_secret VARCHAR(512) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY totp_secret VARCHAR(255) NULL');
    }
};
