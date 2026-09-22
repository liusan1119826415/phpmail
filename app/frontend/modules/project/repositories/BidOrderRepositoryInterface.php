<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface BidOrderRepositoryInterface
{

    public function getList(array $search):array;

    public function getApplyRefund(int $id):array;

    public function getDetail(int $id):array;

    public function confirmSign(int $order_id):bool;

    public function getLogistic(int $order_id):array;

    public function getAfterSales(array $search);
}