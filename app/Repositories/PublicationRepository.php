<?php

namespace App\Repositories;

use App\Models\Publication;
use App\Repositories\Contracts\PublicationRepositoryInterface;

class PublicationRepository extends BaseRepository implements PublicationRepositoryInterface
{
    public function __construct(Publication $model)
    {
        parent::__construct($model);
    }
}
