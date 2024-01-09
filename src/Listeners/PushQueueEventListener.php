<?php

namespace Xww12333\Mengine\Listeners;

use Xww12333\Mengine\Events\PushQueueEvent;
use Xww12333\Mengine\Services\CommissionPoolService;

class PushQueueEventListener
{
    public CommissionPoolService $service;

    /**
     * Create the event listener.
     */
    public function __construct(CommissionPoolService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle the event.
     */
    public function handle(PushQueueEvent $event): bool
    {
        $this->service->pushPool($event->order);

        return true;
    }
}
