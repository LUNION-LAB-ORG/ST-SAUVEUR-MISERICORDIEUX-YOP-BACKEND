<?php

namespace App\Repositories;

use App\Models\WhatsappSubscriber;
use App\Repositories\Contracts\WhatsappSubscriberRepositoryInterface;

class WhatsappSubscriberRepository extends BaseRepository implements WhatsappSubscriberRepositoryInterface
{
    public function __construct(WhatsappSubscriber $model)
    {
        parent::__construct($model);
    }
}
