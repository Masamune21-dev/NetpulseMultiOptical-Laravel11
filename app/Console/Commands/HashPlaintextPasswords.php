<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * NP-5: hash password users yang masih tersimpan plaintext (bukan bcrypt/argon).
 * Idempoten — aman dijalankan berulang.
 */
class HashPlaintextPasswords extends Command
{
    protected $signature = 'users:hash-plaintext-passwords {--dry-run : Hanya hitung, jangan ubah}';

    protected $description = 'Hash kolom users.password yang belum di-hash (tidak diawali $2y$ / $argon)';

    public function handle(): int
    {
        $rows = DB::table('users')
            ->select(['id', 'username', 'password'])
            ->where('password', 'not like', '$2y$%')
            ->where('password', 'not like', '$argon%')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada password plaintext. (0 baris)');
            return self::SUCCESS;
        }

        $this->warn(sprintf('%d password plaintext ditemukan.', $rows->count()));
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $done = 0;
        foreach ($rows as $row) {
            $plain = (string) $row->password;
            if ($plain === '') {
                $this->line(" - #{$row->id} {$row->username}: password kosong, dilewati");
                continue;
            }
            DB::table('users')->where('id', $row->id)->update(['password' => Hash::make($plain)]);
            $done++;
            $this->line(" - #{$row->id} {$row->username}: di-hash");
        }

        $this->info("Selesai: {$done} password di-hash.");
        return self::SUCCESS;
    }
}
