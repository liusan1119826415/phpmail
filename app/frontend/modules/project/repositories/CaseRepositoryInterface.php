<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface CaseRepositoryInterface
{

    public function getList(array $search):array;

    public function getCaseLable():array;

    //收藏
    public function toggleFavorite(int $id):bool;

    public function detail(int $id):array;





}