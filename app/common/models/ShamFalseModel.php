<?php

namespace app\common\models;

use Illuminate\Database\Eloquent\Model;

/**
 * 作用：用于暂时的存储、展示
 * Class ShamFalseModel
 * @package app\common\models
 */
class ShamFalseModel extends Model
{

    protected static $unguarded = true;

    public function __construct(array $attributes = [])
    {
        $this->setInitialAttributes($attributes);

        parent::__construct([]);
    }

    /**
     * 模型参数初始赋值
     * @param $data
     */
    protected function setInitialAttributes($data)
    {
        if (!empty($data)) {
            $this->setRawAttributes($data);
        }
    }

//    public function toArray()
//    {
//        return array_merge($this->getAttributes(), $this->relationsToArray());
//    }


    public function push()
    {
        return true;
    }

    public function save(array $options = [])
    {
        return true;
    }
}