<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge log lokasi kurir setiap hari jam 3 pagi
Schedule::command('kurir:purge-location-logs')->dailyAt('03:00');

// Retensi notifikasi: Hapus notifikasi yang sudah dibaca >90 hari setiap hari jam 03:30
Schedule::command('notifications:cleanup')->dailyAt('03:30');
