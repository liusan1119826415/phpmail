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

class SupplierAgree  extends BaseStepFactory
{
    public function getTitle()
    {

       $name = '供应商受理';

        return $name;
    }

    public function getDescription()
    {
        if($this->model->order->order_type == 1){
            if($this->model->status == -1){
                return $this->model->reject_time->toDateTimeString();
            }elseif($this->model->progress_status == 1 || $this->model->progress_status == 2){
                return $this->model->supplier_pass_time->toDateTimeString();
            }
        }else{
            if($this->model->status == -1){
                return $this->model->reject_time->toDateTimeString();
            }elseif($this->model->status == 1){
                return $this->model->supplier_pass_time->toDateTimeString();
            }
        }


       return "";

    }

    public function getStatus()
    {
        if($this->model->order->order_type == 1){
            if(in_array($this->model->status,[-1,1]) || $this->model->progress_status == 1){
                return "finish";
            } elseif ($this->processStatus()) {
                return 'process';
            } elseif ($this->waitStatus()) {
                return 'wait';
            }
        }else{
            if(in_array($this->model->status,[-1,1])){
                return "finish";
            } elseif ($this->processStatus()) {
                return 'process';
            } elseif ($this->waitStatus()) {
                return 'wait';
            }
        }

    }

    public function isShow()
    {
        return true;
    }

    public function waitStatus()
    {
        return $this->model->status < 1;
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