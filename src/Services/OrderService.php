<?php

namespace Xww12333\Mengine\Services;

use Xww12333\Mengine\Core\Order;

class OrderService
{
    public function setOrder($uuid, $oid, $symbol, $transaction, $volume, $price): Order
    {
        return new Order($uuid, $oid, $symbol, $transaction, $volume, $price);
    }
}
