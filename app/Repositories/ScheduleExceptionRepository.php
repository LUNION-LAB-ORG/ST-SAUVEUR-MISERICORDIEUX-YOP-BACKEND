<?php

namespace App\Repositories;

use App\Models\ScheduleException;
use App\Repositories\Contracts\ScheduleExceptionRepositoryInterface;

class ScheduleExceptionRepository extends BaseRepository implements ScheduleExceptionRepositoryInterface
{
    public function __construct(ScheduleException $model)
    {
        parent::__construct($model);
    }
}
