<?php

use Illuminate\Support\Facades\Schedule;

// Hapus token HP kedaluwarsa dan jejak GPS lebih dari 180 hari.
Schedule::command('oasis:bersihkan')->dailyAt('02:00');
