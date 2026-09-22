<?php


namespace app\backend\modules\finance\controllers;


use app\backend\modules\member\services\FansItemService;
use app\backend\modules\order\models\Order;
use app\common\components\BaseController;
use app\common\facades\Setting;
use app\common\helpers\PaginationHelper;
use app\common\helpers\QrCodeHelper;
use app\common\helpers\Url;
use Yunshop\MemberTags\Common\TagCondition\firstOrderJudgement;
use Yunshop\Supplier\admin\models\Supplier;
use Yunshop\Supplier\common\models\UniAccountUser;
use Yunshop\Supplier\common\models\WeiQingUsers;

class SupplierController extends BaseController
{
    public function index()
    {
        if (request()->ajax()) {
            $set = Setting::get('plugin.supplier');

            $requestSearch = \YunShop::request()->search;
            $settled_status = $requestSearch['settled_status'];
            if (request()->group_id) {
                $requestSearch['group_id'] = request()->group_id;
            }
            if (request()->category_id) {
                $requestSearch['category_id'] = request()->category_id;
            }
            if(request()->role_id){
                $requestSearch['role_id'] = request()->role_id;
            }
            if ($requestSearch) {
                $requestSearch = array_filter($requestSearch, function ($item) {
                    return !empty($item);
                });
            }
            $pageSize = 10;
            $list = Supplier::getSupplierList($requestSearch, 1)
                ->profileWith()
                ->with([
                    'hasOneUnique',
                    'hasOneMiniApp',
                    'hasOneFans'
                ])
                ->paginate($pageSize)
                ->toArray();

            $list = $this->setFansItem($list);
            foreach ($list['data'] as $key => $value) {

                $list['data'][$key]['avatar'] = empty($value['has_one_member']['avatar']) ? yz_tomedia($value['avatar']) : yz_tomedia($value['has_one_member']['avatar']);
                $list['data'][$key]['settled_time'] = $value['created_at'] ? $value['created_at'] : '';
                $list['data'][$key]['expire_time'] = $value['created_at'] ? date('Y-m-d h:i:s', (strtotime($value['created_at']) + 31536000 + $value['settlement_time'])) : '';
                $list['data'][$key]['settled_status'] = $this->getSettledStatus(strtotime($list['data'][$key]['expire_time']));
                //获取成交订单数量
                $result = Order::where('supp_id', $value['id'])
                    ->where('status', 1)
                    ->whereIn('orderStatus', [1, 2, 3])
                    ->selectRaw('SUM(goods_price) as total_price, COUNT(*) as total_count')
                    ->first();
                $list['data'][$key]['order_completed_num'] = $result->total_count?:0;
                $list['data'][$key]['order_completed_price'] = $result->total_price?:0;
            }
            $list['set'] = $set;
            $list['requestSearch'] = $requestSearch;
            $list['var'] = \YunShop::app()->get();
            $list['exist_diyform'] = app('plugins')->isEnabled('diyform');
            return $this->successJson('ok', $list);
        }

        return view('finance.supplier.list',
        [
            'group_id' => request()->group_id,
            'category_id' => request()->category_id,
        ])->render();
    }

    public function updateSupplier()
    {
        $supplier_list = Supplier::getSupplierList([], 1)->get();
        $supplier_list->map(function ($supplier) {
            WeiQingUsers::updateType($supplier->uid);
            $acount_user = UniAccountUser::select()->whereUid($supplier->uid)->first();
            if ($acount_user) {
                $acount_user->role = 'clerk';
                $acount_user->save();
            }
        });
        dd('更改供应商数据成功');
        exit;
    }

    public function changeOpen()
    {
        $id = (int)request()->id;
        $supplier = Supplier::find($id);
        $supplier->insurance_status = 1;

        if ($supplier->save()) {
            return $this->successJson('开启成功');
        } else {
            return $this->errorJson('开启失败');
        }
    }

    public function changeClose()
    {
        $id = (int)request()->id;
        $supplier = Supplier::find($id);
        $supplier->insurance_status = 0;

        if ($supplier->save()) {
            return $this->successJson('关闭保单');
        } else {
            return $this->errorJson('关闭失败');
        }
    }

    private function getSettledStatus($new_apply_time)
    {
        /**
         * -1 = 已过期
         * 1 = 入驻
         * 2 = 即将过期
         */
        $status = '-1';
        $time = time();

        if (!$new_apply_time) {
            return ''; // 已过期
        }
        if ($time > $new_apply_time) {
            $status = '已过期'; // 已过期
        }
        if ($time <= $new_apply_time && ($new_apply_time - $time) > 2592000) {
            $status = '正常'; // 入驻
        }
        if (($new_apply_time - $time) <= 2592000 && ($new_apply_time - $time) > 0) {
            $status = '即将过期'; // 即将过期
        }
        return $status;
    }

    public function applyTimeView()
    {
        return view('Yunshop\Supplier::admin.supplier.supplier_add_time', [])->render();
    }

    public function addApplyTime()
    {
        $id = request()->input('data.id');
        $time = (int) request()->input('data.day') * 3600 * 24;
        $data = Supplier::uniacid()->where('id', $id)->first();
        if ($data) {
            $data->settlement_time += $time;
            $data->save();
            return $this->successJson('更新成功');
        }
        return $this->errorJson('更新失败');
    }

    public function setFansItem($list)
    {
        foreach ($list['data'] as $key => $item) {
            if (isset($item['has_one_unique'])) {
                $list['data'][$key]['fans_item'] = substr($item['has_one_unique']['unionid'], 0, 4) . '**' . substr($item['has_one_unique']['unionid'], -6);
            }
            if (isset($item['has_one_mini_app'])) {
                $list['data'][$key]['fans_item'] = substr($item['has_one_mini_app']['openid'], 0, 4) . '**' . substr($item['has_one_mini_app']['openid'], -6);
            }
            if (isset($item['has_one_fans'])) {
                $list['data'][$key]['fans_item'] = substr($item['has_one_fans']['openid'], 0, 4) . '**' . substr($item['has_one_fans']['openid'], -6);
            }
            if (!empty($item['mobile'])) {
                $list['data'][$key]['fans_item'] = $item['mobile'];
            }
            if (!empty($item['has_one_member']['nickname'])) {
                $list['data'][$key]['fans_item'] = $item['has_one_member']['nickname'];
            }

            if (empty($item['has_one_member']['avatar']) && !empty(Setting::get('shop.member')['headimg_url'])) {
                $list['data'][$key]['avatar'] = yz_tomedia(Setting::get('shop.member')['headimg_url']);
            }
            if (empty($item['has_one_member']['avatar']) && empty(Setting::get('shop.member')['headimg_url'])) {
                $list['data'][$key]['avatar'] = yz_tomedia(Setting::get('shop.shop')['logo']);
            }
        }
        return $list;
    }

    public function getQrCode()
    {
        $supplier_id = request()->get('supplier_id');
        $url = yzAppFullUrl('alone_template/supplier/home', ['supplier_alone_id' => $supplier_id, 'i' => \YunShop::app()->uniacid]);
        $data['url'] =  (new QrCodeHelper($url, 'app/public/qr/supplier'))->url();
        return $this->successJson('', $data);
    }
}
