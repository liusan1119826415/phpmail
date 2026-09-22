<?php

namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\ProjectRepositoryInterface;

class ProjectService
{

    private ProjectRepositoryInterface $projectRepository;

    public function __construct(ProjectRepositoryInterface $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function createProject(array $request_data)
    {
         return $this->projectRepository->createProject($request_data);
    }

    public function editProject(array $request_data)
    {
        return $this->projectRepository->editProject($request_data);
    }

    public function getFloors(int $project_id)
    {
        return $this->projectRepository->getFloors($project_id);
    }


    public function switchActiveProject(int $project_id, int $floor_id = null, int $space_id = null)
    {
        return $this->projectRepository->switchActiveProject($project_id,$floor_id,$space_id);
    }

    public function getActiveProject()
    {
        return $this->projectRepository->getActiveProject();
    }

    public function getMyProject()
    {
        return $this->projectRepository->getMyProject();
    }

    public function logout(int $project_id)
    {
        return $this->projectRepository->logout($project_id);
    }

    public function createFloor(int $project_id,string $floor_name)
    {
        return $this->projectRepository->createFloor($project_id,$floor_name);
    }


    public function updatePrice(int $project_id,float $ratio)
    {
        return $this->projectRepository->updatePrice($project_id,$ratio);
    }


    public function getList(array $search)
    {
        return $this->projectRepository->getList($search);
    }

    public function getProvinceCity(int $id)
    {
        return $this->projectRepository->getProvinceCity($id);
    }
    //项目加入回收站

    public function delete(int $id)
    {
        return $this->projectRepository->delete($id);
    }

    //更改项目状态
    public function updateStatus(int $id,int $status)
    {
        return $this->projectRepository->updateStatus($id,$status);
    }

    public function copyProject(int $id)
    {
        return $this->projectRepository->copyProject($id);
    }

    //项目详情
    public function detail(int $id)
    {
        return $this->projectRepository->detail($id);
    }


    public function planDelete(int $id)
    {
        return $this->projectRepository->planDelete($id);
    }


    public function restore(int $id)
    {
        return $this->projectRepository->restore($id);
    }

    public function realDelete(int $id)
    {
        return $this->projectRepository->realDelete($id);
    }

    public function savePlan()
    {
        return $this->projectRepository->savePlan();
    }

    public function getProjectData($space_id)
    {
        return $this->projectRepository->getProjectData($space_id);
    }

    public function updateFloorCad($data)
    {
        return $this->projectRepository->updateFloorCad($data);
    }


}