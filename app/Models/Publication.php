<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Publication de la communauté (album photo, vidéo, témoignage, article).
 */
class Publication extends Model
{
    use SoftDeletes, HasPublicationStatus, HasUniqueSlug;

    public const TYPES = ['photo', 'video', 'text'];

    protected $guarded = [];

    protected $casts = [
        'gallery'      => 'array',
        'is_featured'  => 'bool',
        'likes_count'  => 'int',
        'sort_order'   => 'int',
        'published_at' => 'datetime',
        'allow_comments' => 'bool',
        'show_likes'     => 'bool',
    ];

    protected $attributes = [
        'gallery' => '[]',
    ];

    protected static function booted(): void
    {
        static::creating(function (Publication $publication) {
            $publication->published_at ??= now();
        });
    }

    /** Publiée et date de publication atteinte. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->published()
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isVisible(): bool
    {
        return $this->isPublished() && (!$this->published_at || $this->published_at->lte(now()));
    }

    public function comments()
    {
        return $this->hasMany(PublicationComment::class);
    }

    public function likes()
    {
        return $this->hasMany(PublicationLike::class);
    }

    /** Identifiant YouTube extrait de video_url (youtu.be, watch?v=, embed, shorts, live). */
    public function youtubeId(): ?string
    {
        if (!$this->video_url) {
            return null;
        }

        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~youtube(?:-nocookie)?\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->video_url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** Temps de lecture estimé (≈ 200 mots par minute, minimum 1). */
    public function readingMinutes(): int
    {
        $text = trim(strip_tags(implode(' ', array_filter([$this->lead, $this->body, $this->quote]))));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

        return max(1, (int) round($words / 200));
    }
}
