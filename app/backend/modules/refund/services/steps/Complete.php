<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/14
 * Time: 18:19
 */

namespace app\backend\modules\refund\services\steps;


use app\common\models\refund\RefundApply;
use app\common\services\steps\BaseStepFactory;

class Complete  extends BaseStepFactory
{
    public function getTitle()
    {

       $name = '售后完成';

        return $name;
    }

    public function getDescription()
    {
        if($this->model->progress_status == 2){
            return $this->model->refund_time?$this->model->refund_time->toDateTimeString():"";
        }

       return "";

    }

    public function getStatus()
    {
        if(in_array($this->model->progress_status,[2])){
            return "finish";
        } elseif ($this->processStatus()) {
            return 'process';
        } elseif ($this->waitStatus()) {
            return 'wait';
        }
    }

    public function isShow()
    {
        return true;
    }

    public function waitStatus()
    {
        return $this->model->progress_status < 2;
    }

    public function processStatus()
    {
        return false;
    }

    public function finishStatus()
    {
        return $this->model->status == RefundApply::COMPLETE ||
            $this->model->status == RefundApply::CONSENSUS ||
            $this->model->status == RefundApply::CLOSE;
    }

    public function sort()
    {
        return 99999;
    }
}