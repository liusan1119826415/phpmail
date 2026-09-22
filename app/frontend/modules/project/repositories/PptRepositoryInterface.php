<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface PptRepositoryInterface
{

    public function getTemplate():array;

    public function generatePpt(array $space_ids,string $project_name,int $template_id,int $project_id):array;

    public function savePpt(int $id,string $project_name):bool;

    public function export(int $id):array;

    public function delete(int $id):bool;


}