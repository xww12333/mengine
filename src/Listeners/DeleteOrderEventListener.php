<?php

namespace Xww12333\Mengine\Listeners;

use Xww12333\Mengine\Events\DeleteOrderEvent;
use Xww12333\Mengine\Services\CommissionPoolService;

class DeleteOrderEventListener
{
    public $service;

    /**
     * Create the event listener.
     */
    public function __construct(CommissionPoolService $service)
    {
        $this->service = $service;
    }

    /**
     * Handle the event.
     *
     * @param DeleteOrderEvent $event
     * @return bool
     */
    public function handle(DeleteOrderEvent $event)
    {
        $this->service->deletePoolOrder($event->order);

        return true;
    }
}
