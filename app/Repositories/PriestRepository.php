<?php

namespace App\Repositories;

use App\Models\Priest;
use App\Repositories\Contracts\PriestRepositoryInterface;

class PriestRepository extends BaseRepository implements PriestRepositoryInterface
{
    public function __construct(Priest $model)
    {
        parent::__construct($model);
    }
}
