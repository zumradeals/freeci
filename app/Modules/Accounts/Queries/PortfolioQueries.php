<?php

namespace App\Modules\Accounts\Queries;

use Illuminate\Support\Facades\DB;

/** Lecture des réalisations d'un freelance (la plus récente d'abord). */
final class PortfolioQueries
{
    /** @return list<array{id: string, title: string, description: ?string, year: ?int}> */
    public function active(string $userId): array
    {
        return DB::table('portfolio_items')->where('user_id', $userId)->where('state', 'active')->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'title', 'description', 'year'])->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'description' => $r->description, 'year' => $r->year])->all();
    }
}
