<?php

namespace App\Repositories;

use App\Models\PublicationComment;
use App\Repositories\Contracts\PublicationCommentRepositoryInterface;

class PublicationCommentRepository extends BaseRepository implements PublicationCommentRepositoryInterface
{
    public function __construct(PublicationComment $model)
    {
        parent::__construct($model);
    }
}
