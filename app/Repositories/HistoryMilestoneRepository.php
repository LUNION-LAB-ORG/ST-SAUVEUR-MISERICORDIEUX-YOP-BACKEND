<?php

namespace App\Repositories;

use App\Models\HistoryMilestone;
use App\Repositories\Contracts\HistoryMilestoneRepositoryInterface;

class HistoryMilestoneRepository extends BaseRepository implements HistoryMilestoneRepositoryInterface
{
    public function __construct(HistoryMilestone $model)
    {
        parent::__construct($model);
    }
}
