<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface DoorOrderRepositoryInterface
{

    public function reqDoor(array $data):bool;


    public function getProjectService(int $project_id):array;

    public function getProjectList():array;

    public function getList(array $search):array;

    public function getDetail(int $id):array;

    public function addAmount($id,$amount,$expense_type):bool;

    public function getServiceFee(int $supplier_id):array;

    public function acceptanceCheck(int $id):bool;

    public function getDoorFee(int $order_id):array;

    public function getAfterSales(array $search);


}