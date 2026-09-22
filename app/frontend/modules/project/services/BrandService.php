<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\BrandRepositoryInterface;
class BrandService
{
    private BrandRepositoryInterface $brandRepository;

    public function __construct(BrandRepositoryInterface $brandRepository)
    {
        $this->brandRepository = $brandRepository;
    }


    public function conditions():array
    {
        return $this->brandRepository->conditions();
    }

    public function search(array $filters):array
    {
        return $this->brandRepository->search($filters);
    }

    public function detail(int $supplier_id):array
    {
        return $this->brandRepository->detail($supplier_id);
    }

    public function getBrandGoods(int $supplier_id):array
    {
        return $this->brandRepository->getBrandGoods($supplier_id);
    }

    public function getBrandOther(int $supplier_id,int $other_type):array
    {
        return $this->brandRepository->getBrandOther($supplier_id,$other_type);
    }


    public function follow(int $supplier_id,int $follow_type):bool
    {
        return $this->brandRepository->follow($supplier_id,$follow_type);
    }




}