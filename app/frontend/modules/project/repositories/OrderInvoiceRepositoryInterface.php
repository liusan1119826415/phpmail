<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface OrderInvoiceRepositoryInterface
{

    public function storeTitle(array $data):bool;

    public function updateTitle(int $id,string $title_name):bool;

    public function destroyTitle(int $id):bool;

    public function getTitleList(int $order_id):array;

    public function setDefault(int $id):bool;

    public function apply(array $data):bool;

    public function updateApply(array $data):bool;

    public function getList(array $search):array;

    public function getDetail(int $id):array;

    public function revoke(int $id):bool;

    public function getNotInvoice($name):array;

}