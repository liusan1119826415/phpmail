<?php

namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\FactoryInspectionRepositoryInterface;

class FactoryInspectionService
{

    private FactoryInspectionRepositoryInterface $factoryInspectionRepository;

    public function __construct(FactoryInspectionRepositoryInterface $factoryInspectionRepository)
    {
        $this->factoryInspectionRepository = $factoryInspectionRepository;
    }



    public function getList(array $search)
    {
        return $this->factoryInspectionRepository->getList($search);
    }

    public function applyFor(array $request_data):bool
    {
        return $this->factoryInspectionRepository->applyFor($request_data);
    }

    public function getApplyData(int $id):array
    {
        return $this->factoryInspectionRepository->getApplyData($id);
    }

    public function getDetail(int $project_id):array
    {
        return $this->factoryInspectionRepository->getDetail($project_id);
    }

    public function searchBrand(string $name):array
    {
        return $this->factoryInspectionRepository->searchBrand($name);
    }


    public function delete(int $id):array
    {
        return $this->factoryInspectionRepository->delete($id);
    }

    public function cancelApply(int $id):bool
    {
        return $this->factoryInspectionRepository->cancelApply($id);
    }





}