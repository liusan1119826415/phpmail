<?php


namespace app\frontend\modules\project\services;



use app\frontend\modules\project\repositories\OrderInvoiceRepositoryInterface;
class OrderInvoiceService
{

    private OrderInvoiceRepositoryInterface $orderInvoiceRepository;

    public function __construct(OrderInvoiceRepositoryInterface $orderInvoiceRepository)
    {
        $this->orderInvoiceRepository = $orderInvoiceRepository;
    }


    public function storeTitle($data):bool
    {
        return $this->orderInvoiceRepository->storeTitle($data);
    }

    public function updateTitle($id,$title_name):bool
    {
        return $this->orderInvoiceRepository->updateTitle($id,$title_name);
    }

    public function destroyTitle($id):bool
    {
        return $this->orderInvoiceRepository->destroyTitle($id);
    }

    public function getTitleList($order_id):array
    {
        return $this->orderInvoiceRepository->getTitleList($order_id);
    }

    public function setDefault($id):bool
    {
        return $this->orderInvoiceRepository->setDefault($id);
    }

    public function apply($data):bool
    {
        return $this->orderInvoiceRepository->apply($data);
    }

    public function updateApply($data):bool
    {
        return $this->orderInvoiceRepository->updateApply($data);
    }

    public function getList($search):array
    {
        return $this->orderInvoiceRepository->getList($search);
    }

    public function getDetail($id):array
    {
        return $this->orderInvoiceRepository->getDetail($id);
    }

    public function revoke($id):bool
    {
        return $this->orderInvoiceRepository->revoke($id);
    }

    public function getNotInvoice($name):array
    {
        return $this->orderInvoiceRepository->getNotInvoice($name);
    }


}