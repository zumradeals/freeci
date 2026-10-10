<?php

namespace App\Modules\Admin\Support;

use Illuminate\Support\Carbon;

/**
 * Période d'une statistique ou d'un export (F-17) : jours entiers à l'heure d'Abidjan, bornes [début, fin[ ; au plus 400 jours ; jamais dans le futur.
 * Une saisie invalide ne produit jamais d'erreur silencieuse : la période par défaut (30 jours) est appliquée et `notice` l'explique.
 */
final class StatsPeriod
{
    public const TZ = 'Africa/Abidjan';

    public const MAX_DAYS = 400;

    public const PRESETS = ['7j' => ['7 jours', 7], '30j' => ['30 jours', 30], '90j' => ['90 jours', 90], '12m' => ['12 mois', 365]];

    private function __construct(
        public readonly string $key, public readonly Carbon $from, public readonly Carbon $to, public readonly ?string $notice,
    ) {}

    /** @param array<string, mixed> $in */
    public static function fromInput(array $in, ?Carbon $now = null): self
    {
        $now = ($now ?? now())->copy()->timezone(self::TZ);
        $today = $now->copy()->startOfDay();
        $key = (string) ($in['periode'] ?? '30j');
        if ($key === 'perso') {
            $d = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::createFromFormat('!Y-m-d', $v, self::TZ) : null;
            $from = $d($in['du'] ?? null);
            $to = $d($in['au'] ?? null);
            if ($from === null || $to === null || $from->format('Y-m-d') !== $in['du'] || $to->format('Y-m-d') !== $in['au']) {
                return self::preset('30j', $today, 'Dates invalides : la période par défaut (30 jours) est affichée.');
            }
            if ($from->gt($to) || $to->gt($today) || $from->diffInDays($to) + 1 > self::MAX_DAYS) {
                return self::preset('30j', $today, 'Période refusée (début après la fin, date future, ou plus de '.self::MAX_DAYS.' jours) : la période par défaut (30 jours) est affichée.');
            }

            return new self('perso', $from, $to->copy()->addDay(), null);
        }

        return self::preset(array_key_exists($key, self::PRESETS) ? $key : '30j', $today, null);
    }

    private static function preset(string $key, Carbon $today, ?string $notice): self
    {
        $days = self::PRESETS[$key][1];

        return new self($key, $today->copy()->subDays($days - 1), $today->copy()->addDay(), $notice);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to);
    }

    /** Période précédente de même durée, juste avant celle-ci. @return array{0: Carbon, 1: Carbon} */
    public function previous(): array
    {
        return [$this->from->copy()->subDays($this->days()), $this->from->copy()];
    }

    /** Dernier jour inclus. */
    public function lastDay(): Carbon
    {
        return $this->to->copy()->subDay();
    }

    public function label(): string
    {
        return 'du '.$this->from->translatedFormat('j M Y').' au '.$this->lastDay()->translatedFormat('j M Y');
    }

    /** @return array<string, string> paramètres d'URL pour reconduire cette période */
    public function query(): array
    {
        return $this->key === 'perso' ? ['periode' => 'perso', 'du' => $this->from->format('Y-m-d'), 'au' => $this->lastDay()->format('Y-m-d')] : ['periode' => $this->key];
    }
}
