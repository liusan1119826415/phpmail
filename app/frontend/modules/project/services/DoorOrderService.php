<?php


namespace app\frontend\modules\project\services;



use app\frontend\modules\project\repositories\DoorOrderRepositoryInterface;
class DoorOrderService
{

    private DoorOrderRepositoryInterface $doorOrderRepository;

    public function __construct(DoorOrderRepositoryInterface $doorOrderRepository)
    {
        $this->doorOrderRepository = $doorOrderRepository;
    }


    public function reqDoor($data):bool
    {
        return $this->doorOrderRepository->reqDoor($data);
    }

    public function getProjectService($project_id):array
    {
        return $this->doorOrderRepository->getProjectService($project_id);
    }

    public function getProjectList():array
    {
        return $this->doorOrderRepository->getProjectList();
    }

    public function getList($search):array
    {
        return $this->doorOrderRepository->getList($search);
    }

    public function getDetail($id):array
    {
        return $this->doorOrderRepository->getDetail($id);
    }

    public function addAmount($id,$amount,$expense_type):bool
    {
        return $this->doorOrderRepository->addAmount($id,$amount,$expense_type);
    }


    public function getServiceFee($supplier_id):array
    {
        return $this->doorOrderRepository->getServiceFee($supplier_id);
    }

    public function acceptanceCheck($id):bool
    {
        return $this->doorOrderRepository->acceptanceCheck($id);
    }

    public function getDoorFee($order_id):array
    {
        return $this->doorOrderRepository->getDoorFee($order_id);
    }


    public function getAfterSales($search)
    {
        return $this->doorOrderRepository->getAfterSales($search);
    }











}