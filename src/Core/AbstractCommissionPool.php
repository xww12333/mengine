<?php

namespace Xww12333\Mengine\Core;

abstract class AbstractCommissionPool
{
    /**
     * in pool.
     */
    abstract public function pushPool(Order $order);

    /**
     * out pool.
     */
    abstract public function deletePoolOrder(Order $order);
}
