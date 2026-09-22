<?php


namespace app\frontend\modules\project\services;



use app\common\exceptions\AppException;
use app\frontend\modules\memberCart\MemberCartCollection;
use app\frontend\modules\project\repositories\OrderRepositoryInterface;
class PreOrderService
{

    private OrderRepositoryInterface $OrderRepository;

    public function __construct(OrderRepositoryInterface $OrderRepository)
    {
        $this->OrderRepository = $OrderRepository;
    }


    public function preOrder(int $project_id)
    {
        return $this->OrderRepository->preOrder($project_id);
    }


    public function createOrder(int $project_id)
    {
        return $this->OrderRepository->createOrder($project_id);
    }


    public function getList(array $search)
    {
        return $this->OrderRepository->getList($search);
    }


    public function getRecycleOrder(array $search)
    {
        return $this->OrderRepository->getRecycleOrder($search);
    }

    public function detail(int $order_id)
    {
        return $this->OrderRepository->detail($order_id);
    }

    public function delete(int $order_id)
    {
        return $this->OrderRepository->delete($order_id);
    }

    public function recycle(int $order_id)
    {
        return $this->OrderRepository->recycle($order_id);
    }

    public function getDrawList(array $search)
    {
        return $this->OrderRepository->getDrawList($search);
    }

    public function batchConfirm(array $ids)
    {
        return $this->OrderRepository->batchConfirm($ids);
    }

    public function getOrderGoods(array $search)
    {
        return $this->OrderRepository->getOrderGoods($search);
    }

    public function getProductSchedule(int $project_id)
    {
        return $this->OrderRepository->getProductSchedule($project_id);
    }


    public function getAfterSalesOrder(array $search)
    {
        return $this->OrderRepository->getAfterSalesOrder($search);
    }

    public function getAfterSalesOrderDetail(int $order_id)
    {
        return $this->OrderRepository->getAfterSalesOrderDetail($order_id);
    }

    public function getLogisticsTrack(int $order_id)
    {
        return $this->OrderRepository->getLogisticsTrack($order_id);
    }

    public function getInstallTrack(int $order_id)
    {
        return $this->OrderRepository->getInstallTrack($order_id);
    }

    public function getRemittanceResult(int $order_pay_id)
    {
        return $this->OrderRepository->getRemittanceResult($order_pay_id);
    }

    public function confirmSign(int $order_main_id)
    {
        return $this->OrderRepository->confirmSign($order_main_id);
    }

    public function cancelConfirm(int $order_goods_id)
    {
        return $this->OrderRepository->cancelConfirm($order_goods_id);
    }

    public function getAddress()
    {
        return $this->OrderRepository->getAddress();
    }

    public function submitOrderSuccess(int $order_id)
    {
        return $this->OrderRepository->submitOrderSuccess($order_id);
    }

    public function getPickupPoint(int $order_id)
    {
        return $this->OrderRepository->getPickupPoint($order_id);
    }

    public function deliveryBill(int $order_id)
    {
        return $this->OrderRepository->deliveryBill($order_id);
    }

    public function verifyBeforeOrder(int $project_id)
    {
        return $this->OrderRepository->verifyBeforeOrder($project_id);
    }










}