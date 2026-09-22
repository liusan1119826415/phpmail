<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\PptRepositoryInterface;
class PptService
{
    private PptRepositoryInterface $pptRepository;

    public function __construct(PptRepositoryInterface $pptRepository)
    {
        $this->pptRepository = $pptRepository;
    }



    public function getTemplate():array
    {
        return $this->pptRepository->getTemplate();
    }


    public function generatePpt($space_ids,$project_name,$template_id,$project_id):array
    {
        return $this->pptRepository->generatePpt($space_ids,$project_name,$template_id,$project_id);
    }


    public function export($id):array
    {
        return $this->pptRepository->export($id);
    }


    public function savePpt($id,$project_name):bool
    {
        return $this->pptRepository->savePpt($id,$project_name);
    }

    public function delete($id):bool
    {
        return $this->pptRepository->delete($id);
    }








}