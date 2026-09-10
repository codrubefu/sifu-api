<?php

namespace App\Articles\Jobs;

use App\Articles\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TransitionArticlePublicationStatus implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Article::query()->withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['status' => 'published', 'updated_at' => now()]);

        Article::query()->withoutGlobalScopes()
            ->whereIn('status', ['scheduled', 'published'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }
}
