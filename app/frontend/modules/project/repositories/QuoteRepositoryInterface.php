<?php

namespace app\frontend\modules\project\repositories;
use app\common\models\project\Project;

interface QuoteRepositoryInterface
{

    public function saveTemplate(string $field_data):bool;

    public function getTemplate():array;







}