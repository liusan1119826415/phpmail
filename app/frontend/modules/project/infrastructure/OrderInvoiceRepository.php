<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;


use app\common\models\kefu\ServiceUser;
use app\common\models\project\InvoiceTitles;
use app\common\models\project\OrderInvoiceUploads;
use app\common\models\project\OrderInvoiceV2;
use app\frontend\models\Order;
use app\frontend\modules\project\repositories\OrderInvoiceRepositoryInterface;
use Carbon\Carbon; // 确保顶部 use Carbon
class OrderInvoiceRepository extends BaseRepository implements OrderInvoiceRepositoryInterface
{


    public function storeTitle(array $data): bool
    {
        try {
            $memberId = \YunShop::app()->getMemberId();
            if ($data['is_default'] == 1) {
                InvoiceTitles::where('member_id', $memberId)->where('title_type', 1)->update(
                    [
                        'is_default' => 0
                    ]
                );
            }

            $data['member_id'] = $memberId;
            InvoiceTitles::create($data);
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }

    }


    public function updateTitle(int $id, string $title_name): bool
    {
        try {
            $InvoiceTitles = InvoiceTitles::find($id);
            if (!$InvoiceTitles) {
                throw new AppException("未找到数据");
            }

            $InvoiceTitles->title_name = $title_name;
            $InvoiceTitles->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }


    public function destroyTitle(int $id): bool
    {
        // TODO: Implement destroyTitle() method.

        try {
            $InvoiceTitles = InvoiceTitles::find($id);
            if (!$InvoiceTitles) {
                throw new AppException("未找到数据");
            }

            $InvoiceTitles->delete();
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }
    }


    public function setDefault(int $id): bool
    {
        try {
            $memberId = \YunShop::app()->getMemberId();

            // 重置所有默认
            InvoiceTitles::where('member_id', $memberId)->where('title_type', 1)->update(['is_default' => 0]);

            // 设置当前为默认
            $title = InvoiceTitles::where('member_id', $memberId)->findOrFail($id);
            $title->is_default = 1;
            $title->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }

    public function getTitleList(int $order_id): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $list = InvoiceTitles::where('member_id', $memberId)->get()->toArray();
        $order = Order::find($order_id);

        if($order->order_type == 1){
            $order_price = $order->goods_price;
            if($order->choose_logistics == 1){
                $order_price+=$order->freight_price;
            }

            if($order->choose_install == 1){
                $order_price+=$order->install_price;
            }

        }

        if($order->order_type == 2){
            $order_price = $order->price;
        }
        if($order->order_type == 5){
            $order_price = $order->goods_price;
            $order_price = $order_price-$order->discount_price;
            if($order->extra_enable == 1){
                $order_price += $order->extra_amount;
            }
        }
        $data['list'] = $list;
        $data['order_sn'] = $order->order_sn;
        $data['order_price'] = $order_price;
        return $data;
    }

    public function apply(array $data): bool
    {
        $data['is_notice'] = request()->input('is_notice', 0);
        $data['content_type'] = request()->input('is_notice', 0);
        $order = Order::where('id', $data['order_id'])->first();

        $data['invoice_amount'] = $order->goods_price + $order->install_price + $order->freight_price;

        $OrderInvoiceV2 = OrderInvoiceV2::where('order_id', $data['order_id'])->where('is_canceled', 0)->first();
        if ($OrderInvoiceV2) {
            throw new AppException('订单已经申请发票');
        }
        $model = new OrderInvoiceV2;
        $data['member_id'] = \YunShop::app()->getMemberId();
        $model->setRawAttributes($data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                //家具
                Order::where('id',$data['order_id'])->update([
                    "is_invoice"=>1
                ]);
                OrderInvoiceUploads::insertInvoiceUpload($model->id, 1, $order->goods_price, $model->invoice_type);
                //物流
                OrderInvoiceUploads::insertInvoiceUpload($model->id, 2, $order->freight_price, $model->invoice_type);
                //安装
                OrderInvoiceUploads::insertInvoiceUpload($model->id, 3, $order->install_price, $model->invoice_type);

                return true;
            } else {
                throw new AppException("发票申请失败");
            }
        }

    }

    public function updateApply(array $data): bool
    {
        $id = request()->input('id');
        $data['is_notice'] = request()->input('is_notice', 0);
        $data['content_type']= request()->input('content_type', 0);
        $model = OrderInvoiceV2::find($id);
        if (!$model) {
            throw new AppException('订单发票不存在');
        }
        $model->setRawAttributes($data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                return true;
            } else {
                throw new AppException("发票修改失败");
            }
        }

    }

    public function revoke(int $id): bool
    {
        $OrderInvoiceV2 = OrderInvoiceV2::find($id);
        if (!$OrderInvoiceV2) {
            throw new AppException('订单发票不存在');
        }
        if ($OrderInvoiceV2->is_canceled == 1) {
            throw new AppException('订单发票已经撤销');
        }


        $OrderInvoiceV2->is_canceled = 1;
        $OrderInvoiceV2->save();
        Order::where('id',$OrderInvoiceV2->order_id)->update([
            "is_invoice"=>0
        ]);
        return true;

    }

    public function getList(array $search): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $query = OrderInvoiceV2::where('member_id', $memberId)->with(['order' => function ($query) {
            $query->select("id", "order_sn", "project_id","order_type")->with(['project' => function ($query) {
                $query->select("id", "name");
            }]);
        }]);
        if($search['project_name']){
            $query->whereHas('order',function ($query) use($search){
                $query->whereHas('project',function ($query)use($search){
                    $query->where('name','like','%'.$search['project_name'].'%');
                });
            });
        }
        if($search['status']){
            $query->where('status',$search['status']);
        }

        $list = $query->orderBy('created_at','desc')->paginate(self::PAGE_SIZE);
        $list->transform(function ($item){
            $item->open_time = $item->open_time?date("y.m.d",$item->open_time):"";
            return $item;
        });

        return $list->toArray();

    }

    public function getDetail(int $id):array
    {
        $res = OrderInvoiceV2::with(['company','uploads'])->find($id);
        if(!$res){
            throw new AppException('发票不存在');
        }

        // created_at 加 10天
        $expireAt = $res->created_at->copy()->addDays(10);

        // 当前时间
        $now = Carbon::now();

        // 计算剩余秒数
        $order = Order::find($res->order_id);
        $res->countdown = $now->diffInSeconds($expireAt, false);
        $res->service_link = ServiceUser::getDistributeService($order->supp_id);
        if($res->uploads){
            foreach ($res->uploads as &$item){
                $item->invoice_file = yz_tomedia($item->invoice_file);
                if($item->invoice_category == 1){
                    $item->name = "家具";
                }elseif($item->invoice_category == 2){
                    $item->name = "物流运输";
                }elseif($item->invoice_category == 3){
                    $item->name = "安装搬运";
                }

            }
        }
        return $res->toArray();


    }

    public function getNotInvoice($name):array
    {
        $query = Order::select("id","order_sn","price","goods_price","discount_price","choose_logistics","choose_install","extra_enable",'extra_amount',"freight_price","install_price","project_id","order_type")->with(['project'=>function($query){
            $query->select("id","name");
        }])->where('is_invoice',0)->whereIn('order_type',[1,2,5])->where('status',3);

        $query->where('uid',\YunShop::app()->getMemberId());

        if($name) {
            $query->whereHas('project', function ($query) use ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            });
        }

        $data = $query->orderBy('created_at','desc')->paginate(self::PAGE_SIZE);
        $data->transform(function ($item){

                if($item->order_type == 1){
                    $order_price = $item->goods_price;
                    if($item->choose_logistics == 1){
                        $order_price+=$item->freight_price;
                    }

                    if($item->choose_install == 1){
                        $order_price+=$item->install_price;
                    }

                }

                if($item->order_type == 2){
                    $order_price = $item->price;
                }
                if($item->order_type == 5){
                    $order_price = $item->goods_price;
                    $order_price = $order_price-$item->discount_price;
                    if($item->extra_enable == 1){
                        $order_price += $item->extra_amount;
                    }
                }

                $item->order_price = $order_price;
              return $item;

        });
        return $data->toArray();
    }


}