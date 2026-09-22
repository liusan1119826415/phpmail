<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\GoodsRepositoryInterface;
class GoodsService
{
    private GoodsRepositoryInterface $goodsRepository;

    public function __construct(GoodsRepositoryInterface $goodsRepository)
    {
        $this->goodsRepository = $goodsRepository;
    }


    public function conditions():array
    {
        return $this->goodsRepository->conditions();
    }

    public function search(array $filters,array $order_field):array
    {
        return $this->goodsRepository->search($filters,$order_field);
    }

    public function previewList(int $project_id):array
    {
        return $this->goodsRepository->previewList($project_id);
    }

    public function updateUnitPrice(array $option_ids,float $price,int $method,int $space_id)
    {
        return $this->goodsRepository->updateUnitPrice($option_ids,$price,$method,$space_id);
    }

    public function updateOption(array $request_data)
    {
        return $this->goodsRepository->updateOption($request_data);
    }

    public function analysis()
    {
        return $this->goodsRepository->analysis();
    }

    public function analysisV2()
    {
        return $this->goodsRepository->analysisV2();
    }

    public function getGoodsOption(int $goods_id)
    {
        return $this->goodsRepository->getGoodsOption($goods_id);
    }

    public function getGoodsInfo(int $goods_id)
    {
        return $this->goodsRepository->getGoodsInfo($goods_id);
    }

    public function getComment()
    {
        return $this->goodsRepository->getComment();
    }




}