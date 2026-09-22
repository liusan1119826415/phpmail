<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\SpaceRepositoryInterface;
class SpaceService
{
    private SpaceRepositoryInterface $spaceRepository;

    public function __construct(SpaceRepositoryInterface $spaceRepository)
    {
        $this->spaceRepository = $spaceRepository;
    }


    public function addSpace(array $data)
    {
        return $this->spaceRepository->addSpace($data);
    }


    public function add3DSpace(array $data)
    {
        return $this->spaceRepository->add3DSpace($data);
    }

    public function updateSpaceNum(int $id,int $num)
    {

        return $this->spaceRepository->updateSpaceNum($id,$num);
    }

    public function getProductList(int $space_id)
    {
        return $this->spaceRepository->getProductList($space_id);
    }

    public function destroy(array $ids,int $space_id)
    {
        return $this->spaceRepository->destroy($ids,$space_id);
    }

    public function del_space(int $id)
    {
        return $this->spaceRepository->del_space($id);
    }


    public function createSpace(int $project_id,int $floor_id,string $space_name)
    {
        return $this->spaceRepository->createSpace($project_id,$floor_id,$space_name);
    }

    public function editSpace(int $space_id,string $space_name)
    {
        return $this->spaceRepository->editSpace($space_id,$space_name);
    }

    public function getProductListV2(int $space_id)
    {
        return $this->spaceRepository->getProductListV2($space_id);
    }

    public function customized()
    {
        return $this->spaceRepository->customized();
    }







}