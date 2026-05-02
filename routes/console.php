<?php

use App\Enums\TorrentStatus;
use App\Jobs\PollTorrentProgress;
use App\Models\Torrent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Torrent::query()
        ->where('status', TorrentStatus::Downloading)
        ->whereNotNull('qbittorrent_hash')
        ->eachById(fn (Torrent $torrent) => PollTorrentProgress::dispatch($torrent));
})
    ->name('poll-torrent-progress')
    ->everyMinute()
    ->withoutOverlapping();
