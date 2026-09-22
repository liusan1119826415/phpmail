<?php


namespace app\frontend\modules\project\services;



use app\frontend\modules\project\repositories\BidOrderRepositoryInterface;
class BidOrderService
{

    private BidOrderRepositoryInterface $bidOrderRepository;

    public function __construct(BidOrderRepositoryInterface $bidOrderRepository)
    {
        $this->bidOrderRepository = $bidOrderRepository;
    }


    public function getList($search):array
    {
        return $this->bidOrderRepository->getList($search);
    }

    public function getApplyRefund($id):array
    {
        return $this->bidOrderRepository->getApplyRefund($id);
    }

    public function getDetail($id):array
    {
        return $this->bidOrderRepository->getDetail($id);
    }

    public function confirmSign($id):bool
    {
        return $this->bidOrderRepository->confirmSign($id);
    }

    public function getLogistic($id):array
    {
        return $this->bidOrderRepository->getLogistic($id);
    }

    public function getAfterSales($search)
    {
        return $this->bidOrderRepository->getAfterSales($search);
    }


}