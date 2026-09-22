<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface OrderRepositoryInterface
{

    public function preOrder(int $project_id);

    public function createOrder(int $project_id);

    public function getList(array $search):array; //获取订单列表

    public function detail(int $order_id):array;  //订单详情

    public function getRecycleOrder(array $search):array; //获取回收站列表

    public function delete(int $order_id):bool;
    public function recycle(int $order_id):bool;

    public function getDrawList(array $search):array; //获取图纸

    //确认图纸
    public function batchConfirm(array $ids):bool;

    //根据订单获取所有楼层的数据
    public function getOrderGoods(array $search):array;

    public function getProductSchedule(int $project_id):array;

    public function getAfterSalesOrder(array $search):array;

    public function getAfterSalesOrderDetail(int $order_id):array;

    //获取物流进度
    public function getLogisticsTrack(int $order_id):array;

    public function getInstallTrack(int $order_id):array;

    public function getRemittanceResult(int $order_pay_id):array;

    public function confirmSign(int $order_main_id):bool;

    public function cancelConfirm(int $order_goods_id):bool;

    public function getAddress():array;

    public function submitOrderSuccess(int $order_id):array;

    public function getPickupPoint(int $order_id):array;

    public function deliveryBill(int $order_id):array;

    public function verifyBeforeOrder(int $project_id):array;




}