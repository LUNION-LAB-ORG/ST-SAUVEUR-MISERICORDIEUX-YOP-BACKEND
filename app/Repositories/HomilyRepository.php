<?php

namespace App\Repositories;

use App\Models\Homily;
use App\Repositories\Contracts\HomilyRepositoryInterface;

class HomilyRepository extends BaseRepository implements HomilyRepositoryInterface
{
    public function __construct(Homily $model)
    {
        parent::__construct($model);
    }
}
