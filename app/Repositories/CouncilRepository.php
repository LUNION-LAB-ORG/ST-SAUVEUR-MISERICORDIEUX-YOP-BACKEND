<?php

namespace App\Repositories;

use App\Models\Council;
use App\Repositories\Contracts\CouncilRepositoryInterface;

class CouncilRepository extends BaseRepository implements CouncilRepositoryInterface
{
    public function __construct(Council $model)
    {
        parent::__construct($model);
    }
}
