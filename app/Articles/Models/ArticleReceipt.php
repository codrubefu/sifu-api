<?php

namespace App\Articles\Models;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'user_id', 'delivered_at', 'viewed_at'])]
class ArticleReceipt extends Model
{
    protected $table = 'article_user_receipts';

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'viewed_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
