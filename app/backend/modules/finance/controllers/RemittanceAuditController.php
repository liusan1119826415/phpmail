<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/6/19
 * Time: 下午7:22
 */

namespace app\backend\modules\finance\controllers;


use app\common\components\BaseController;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use Illuminate\Database\Eloquent\Builder;


class RemittanceAuditController extends BaseController
{
    /**
     * @return string
     * @throws \Throwable
     */
    public function index()
    {
        return view('finance.remittance.audits', ['data' => json_encode($this->getData())])->render();
    }
    public function ajax(){
        return $this->successJson('成功',$this->getData());
    }
    private function getData(){
        $pageSize = (int)request()->input('pagesize',20);

        /**
         * @var RemittanceAuditFlow $remittanceAuditFlow
         */
        $searchParams = request()->input('search');
        $remittanceAuditFlow = RemittanceAuditFlow::first();

        $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->uniacid()->with(['status', 'remittanceRecord' => function ($query) {
            $query->with(['orderPay','member']);
        },'order'=>function($query){
            $query->with(['project']);
        }]);

        if($searchParams['project_name']){
            $processBuilder->whereHas('order',function ($query) use($searchParams){
                $query->whereHas('project',function ($query) use($searchParams){
                    $query->where('name','like','%'.$searchParams['project_name'].'%');
                });
            });
        }

        if($searchParams['pay_sn']){
            $processBuilder->whereHas('remittanceRecord',function ($query) use($searchParams){
                $query->whereHas('orderPay',function ($query) use($searchParams){
                    $query->where('pay_sn',$searchParams['pay_sn']);
                });
            });
        }

        if($searchParams['order_sn']){
            $processBuilder->whereHas('order',function ($query) use($searchParams){
                $query->where('order_sn',$searchParams['order_sn']);
            });
        }

        if($searchParams['payment_stage']){
            $processBuilder->whereHas('remittanceRecord',function ($query) use($searchParams){
                $query->whereHas('orderPay',function ($query) use($searchParams){
                    $query->where('payment_stage',$searchParams['payment_stage']);
                });
            });
        }

        if($searchParams['status_id']){
            $processBuilder->where('status_id',$searchParams['status_id']);
        }
        $amount = (clone $processBuilder)
            ->leftJoin('yz_remittance_record', 'yz_process.model_id', '=', 'yz_remittance_record.id')
            ->leftJoin('yz_order_pay', 'yz_order_pay.id', '=', 'yz_remittance_record.order_pay_id')
            ->sum('yz_order_pay.amount');
        $processList = $processBuilder->orderBy('id','desc')->paginate($pageSize)->toArray();

        foreach ($processList['data'] as $key=>$val){
            $processList['data'][$key]['remittance_record']['report_url'] = yz_tomedia($val['remittance_record']['report_url']);

        }
        $processList['pagesize'] = $pageSize;
        //dd($processList);
        //exit;

        $allStatus = $remittanceAuditFlow->allStatus;

        $data = [
            'remittanceAudits' => $processList,
            'allStatus' => $allStatus,
            'searchParams' => $searchParams,
            'amount' => $amount
        ];
        return $data;
    }
}