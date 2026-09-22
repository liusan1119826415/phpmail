<?php

namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\BidProjectRepositoryInterface;

class BidProjectService
{

    private BidProjectRepositoryInterface $bidProjectRepository;

    public function __construct(BidProjectRepositoryInterface $bidProjectRepository)
    {
        $this->bidProjectRepository = $bidProjectRepository;
    }



    public function getList(array $search)
    {
        return $this->bidProjectRepository->getList($search);
    }

    public function applyFor(array $request_data):int
    {
        return $this->bidProjectRepository->applyFor($request_data);
    }

    public function detail(int $id):array
    {
        return $this->bidProjectRepository->detail($id);
    }

    public function getRecommendBrand(int $project_id):array
    {
        return $this->bidProjectRepository->getRecommendBrand($project_id);
    }

    public function searchBrand(string $name):array
    {
        return $this->bidProjectRepository->searchBrand($name);
    }

    public function cancelApply(int $id):bool
    {
        return $this->bidProjectRepository->cancelApply($id);
    }




}