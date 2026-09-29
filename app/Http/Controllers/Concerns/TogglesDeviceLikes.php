<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * « J'aime » à bascule, un par appareil : l'identifiant client n'est jamais stocké en clair
 * (device_hash = sha256(device_id . app.key)).
 */
trait TogglesDeviceLikes
{
    protected function deviceHash(string $deviceId): string
    {
        return hash('sha256', $deviceId . config('app.key'));
    }

    /**
     * @param class-string<Model> $likeModel
     * @return array{liked: bool, likes_count: int}
     */
    protected function toggleLike(string $likeModel, string $foreignKey, Model $target, string $deviceId): array
    {
        $hash = $this->deviceHash($deviceId);

        return DB::transaction(function () use ($likeModel, $foreignKey, $target, $hash) {
            $existing = $likeModel::where($foreignKey, $target->getKey())->where('device_hash', $hash)->first();

            if ($existing) {
                $existing->delete();
                $liked = false;
            } else {
                try {
                    $likeModel::create([$foreignKey => $target->getKey(), 'device_hash' => $hash]);
                } catch (QueryException $e) {
                    // Double clic concurrent : le « j'aime » existe déjà
                }
                $liked = true;
            }

            $count = $likeModel::where($foreignKey, $target->getKey())->count();
            // Compteur mis à jour sans toucher updated_at
            $target->newQueryWithoutScopes()->whereKey($target->getKey())->toBase()->update(['likes_count' => $count]);

            return ['liked' => $liked, 'likes_count' => $count];
        });
    }

    /** @return array{liked: bool, likes_count: int} */
    protected function deviceLikeStatus(string $likeModel, string $foreignKey, Model $target, string $deviceId): array
    {
        return [
            'liked'       => $likeModel::where($foreignKey, $target->getKey())
                ->where('device_hash', $this->deviceHash($deviceId))->exists(),
            'likes_count' => (int) $target->likes_count,
        ];
    }
}
