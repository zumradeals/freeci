<?php

namespace App\Shared;

use Carbon\CarbonInterface;

/** Dates affichées en heure d'Abidjan (UTC+0, sans changement d'heure), en français. */
final class Dates
{
    public static function format(CarbonInterface $d): string
    {
        return $d->copy()->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
    }

    public static function short(CarbonInterface $d): string
    {
        return $d->copy()->timezone('Africa/Abidjan')->translatedFormat('j M, H:i');
    }

    /** « dans 21 h », « dans 2 j 3 h » ou « dépassé ». */
    public static function until(CarbonInterface $d): string
    {
        $minutes = (int) now()->diffInMinutes($d, false);
        if ($minutes <= 0) {
            return 'dépassé';
        }
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return $days > 0 ? "dans {$days} j {$hours} h" : ($hours > 0 ? "dans {$hours} h" : 'dans moins d’une heure');
    }

    public static function isUrgent(CarbonInterface $d): bool
    {
        return now()->diffInHours($d, false) < 24;
    }
}
