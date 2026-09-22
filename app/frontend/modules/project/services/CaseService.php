<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\CaseRepositoryInterface;
class CaseService
{
    private CaseRepositoryInterface $caseRepository;

    public function __construct(CaseRepositoryInterface $caseRepository)
    {
        $this->caseRepository = $caseRepository;
    }


    public function getList(array $search):array
    {
        return $this->caseRepository->getList($search);
    }

    public function getCaseLable():array
    {
        return $this->caseRepository->getCaseLable();
    }

    public function toggleFavorite(int $id):bool
    {
        return $this->caseRepository->toggleFavorite($id);
    }

    public function detail(int $id):array
    {
        return $this->caseRepository->detail($id);
    }




}