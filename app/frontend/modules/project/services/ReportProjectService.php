<?php

namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\ReportProjectRepositoryInterface;

class ReportProjectService
{

    private ReportProjectRepositoryInterface $reportProjectRepository;

    public function __construct(ReportProjectRepositoryInterface $reportProjectRepository)
    {
        $this->reportProjectRepository = $reportProjectRepository;
    }



    public function getList(array $search)
    {
        return $this->reportProjectRepository->getList($search);
    }

    public function applyFor(array $request_data):bool
    {
        return $this->reportProjectRepository->applyFor($request_data);
    }

    public function detail(int $id):array
    {
        return $this->reportProjectRepository->detail($id);
    }

    public function getRecommendBrand(int $project_id):array
    {
        return $this->reportProjectRepository->getRecommendBrand($project_id);
    }

    public function searchBrand(string $name):array
    {
        return $this->reportProjectRepository->searchBrand($name);
    }

    public function edit(array $request_data):bool
    {
        return $this->reportProjectRepository->edit($request_data);
    }

    //取消报备
    public function cancel(int $id):bool
    {
        return $this->reportProjectRepository->cancel($id);
    }

    public function getSearchData():array
    {
        return $this->reportProjectRepository->getSearchData();
    }

    public function upload():array
    {
        return $this->reportProjectRepository->upload();
    }




}