<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Shared\Money;

/** Lecture seule des services d'un freelance (la gestion des services viendra dans un lot ultérieur). */
final class ListFreelancerServices
{
    /** @return list<array{title:string,slug:string,price:Money,status:string,published:bool}> */
    public function __invoke(User $freelancer): array
    {
        $labels = ['draft' => 'Brouillon', 'in_review' => 'En contrôle', 'published' => 'Publié', 'suspended' => 'Suspendu', 'archived' => 'Archivé'];

        return Service::query()->whereHas('freelanceProfile', fn ($q) => $q->where('user_id', $freelancer->getKey()))
            ->orderByDesc('published_at')->orderBy('title')->get()
            ->map(fn (Service $s) => [
                'title' => $s->title, 'slug' => $s->slug, 'price' => Money::xof($s->price_xof),
                'status' => $labels[$s->status->value], 'published' => $s->status->value === 'published',
            ])->all();
    }
}
