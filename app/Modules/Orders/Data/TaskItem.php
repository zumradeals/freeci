<?php

namespace App\Modules\Orders\Data;

use Carbon\CarbonInterface;

/** Action attendue de l'utilisateur. Classement : échéance réelle d'abord (la plus proche en tête), puis sans échéance. */
final readonly class TaskItem
{
    public function __construct(
        public string $title,
        public string $object,
        public ?CarbonInterface $due,
        public string $dueLabel,
        public string $effect,
        public string $cta,
        public string $url,
        public string $icon,
        public bool $actionable = true,
    ) {}
}
