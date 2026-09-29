<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commentaire d'une publication (modéré avant affichage).
 */
class PublicationComment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'published', 'rejected'];

    protected $guarded = [];

    protected $casts = [
        'publication_id' => 'int',
        'likes_count'    => 'int',
        'replied_at'     => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function likes()
    {
        return $this->hasMany(CommentLike::class, 'comment_id');
    }
}
