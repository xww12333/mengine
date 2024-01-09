<?php

namespace Xww12333\Mengine\Services;

use Xww12333\Mengine\Core\AbstractCommissionPool;
use Xww12333\Mengine\Core\Order;
use Xww12333\Mengine\Events\DeleteOrderSuccEvent;
use Xww12333\Mengine\Events\MatchEvent;
use Xww12333\Mengine\Events\PushQueueEvent;

class CommissionPoolService extends AbstractCommissionPool
{
    /**
     * 放入委托池.
     */
    public function pushPool(Order $order): bool
    {
        $ms_service = new MengineService();
        if ($ms_service->isHashDeleted($order)) {
            return false;
        }

        $ms_service->deleteHashOrder($order);
        $list = $ms_service->getMutexDepth($order->symbol, $order->transaction, $order->price);
        if ($list) {
            $order = $this->matchUp($order, $list); // 撮合
            if (!$order) {
                return false;
            }
        }

        // 深度列表、数量更新、节点更新
        $depth_link = new DepthLinkService();
        $depth_link->pushZset($order);

        $depth_link->pushDepthHash($order);

        $depth_link->pushDepthNode($order);

        event(new PushQueueEvent($order));

        return true;
    }

    /**
     * 撤单从委托池删除.
     */
    public function deletePoolOrder(Order $order): bool
    {
        $link_service = new LinkService($order->node_link);
        $node = $link_service->getNode($order->node);
        if (!$node) {
            return false;
        }
        // order里的volume替换为缓存里节点上的数量,防止order里的数量与当初push的不一致或者部分成交
        $order->volume = $node->volume;

        // 更新委托量
        $depth_link = new DepthLinkService();
        $depth_link->deleteDepthHash($order);

        // 从深度列表里删除
        $depth_link->deleteZset($order);

        // 从节点链上删除
        $depth_link->deleteDepthNode($order);

        // 撤单成功通知
        event(new DeleteOrderSuccEvent($order));

        return true;
    }

    /**
     * 撮合.
     *
     * @param Order $order 下单
     * @param array $list  价格匹配部分
     *
     * @return null|Order
     */
    public function matchUp(Order $order, array $list): ?Order
    {
        // 撮合
        foreach ($list as $match_info) {
            $link_name = $order->symbol.':link:'.$match_info['price'];
            $link_service = new LinkService($link_name);

            $order = $this->matchOrder($order, $link_service);
            if ($order->volume <= 0) {
                break;
            }
        }

        if ($order->volume > 0) {
            return $order;
        }

        return null;
    }

    public function matchOrder($order, $link_service)
    {
        $match_order = $link_service->getFirst();
        if ($match_order) {
            $compare_result = bccomp($order->volume, $match_order->volume);
            switch ($compare_result) {
                case 1:
                    $match_volume = $match_order->volume;
                    $order->volume = bcsub($order->volume, $match_order->volume);
                    $link_service->deleteNode($match_order);
                    $this->deletePoolMatchOrder($match_order);

                    // 撮合成功通知
                    event(new MatchEvent($order, $match_order, $match_volume));

                    // 递归撮合
                    $this->matchOrder($order, $link_service);
                    break;
                case 0:
                    $match_volume = $match_order->volume;
                    $order->volume = bcsub($order->volume, $match_order->volume);
                    $link_service->deleteNode($match_order);
                    $this->deletePoolMatchOrder($match_order);

                    // 撮合成功通知
                    event(new MatchEvent($order, $match_order, $match_volume));
                    break;
                case -1:
                    $match_volume = $order->volume;
                    $match_order->volume = bcsub($match_order->volume, $order->volume);
                    $order->volume = 0;
                    $link_service->setNode($match_order->node, $match_order);

                    // 委托池更新数量重新设置
                    $match_order->volume = $match_volume;
                    $this->deletePoolMatchOrder($match_order);

                    // 撮合成功通知
                    event(new MatchEvent($order, $match_order, $match_volume));
                    break;
                default:
                    break;
            }

            return $order;
        }

        return $order;
    }

    /**
     * 撮合成交更新委托池.
     */
    public function deletePoolMatchOrder($order)
    {
        $depth_link = new DepthLinkService();

        // 更新委托量
        $depth_link->deleteDepthHash($order);

        // 从深度列表里删除
        $depth_link->deleteZset($order);
    }
}
