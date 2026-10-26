<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\LocationPing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oasis:bersihkan {--hari-gps=180 : Umur jejak GPS yang disimpan (hari)}')]
#[Description('Hapus token HP kedaluwarsa dan jejak GPS lama')]
class Cleanup extends Command
{
    public function handle(): int
    {
        $tokens = ApiToken::where('expires_at', '<', now())->delete();
        $pings = LocationPing::where('recorded_at', '<', now()->subDays((int) $this->option('hari-gps')))->delete();

        $this->info("Token dihapus: {$tokens}; titik GPS dihapus: {$pings}.");

        return self::SUCCESS;
    }
}
