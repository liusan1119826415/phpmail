<?php

namespace app\frontend\modules\project\services\order;

use app\common\exceptions\AppException;
use app\common\models\OrderAddress;
use app\common\models\project\InstallOrder;
use app\common\models\project\InstallTracks;
use app\common\modules\pcnotice\Template;
use app\common\models\Order;
use app\frontend\modules\project\models\Project;

/**
 * 订单生命周期服务
 * 负责：删除、回收站恢复、确认验收、获取地址
 */
class OrderLifecycleService
{
    /**
     * 删除订单（软删除）
     */
    public function delete(int $id): bool
    {
        try {
            $order = Order::find($id);
            if ($order->status != -1) {
                throw new AppException("请取消订单再删除");
            }
            if (!$order) {
                throw new AppException("未找到订单");
            }
            $order->delete();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 从回收站恢复订单
     */
    public function recycle(int $id): bool
    {
        try {
            $order = Order::find($id);
            if ($order->status != -1) {
                throw new AppException("请取消订单再删除");
            }
            if (!$order) {
                throw new AppException("未找到订单");
            }
            $order->is_member_deleted = 0;
            $order->save();
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 确认验收
     */
    public function confirmSign(int $order_main_id): bool
    {
        try {
            $order = Order::find($order_main_id);
            if (!$order) {
                throw new AppException("订单未找到");
            }

            $order->status = 3;
            $order->finish_time = time();
            $order->save();

            InstallOrder::where('order_id', $order_main_id)->update(["install_status" => 2]);

            Order::where('parent_id', $order_main_id)->update([
                "status" => 3,
                "finish_time" => time()
            ]);

            $data['current_status'] = 3;
            $data['description'] = "已验收完毕。感谢您在帮米购物，欢迎再次光临。";
            InstallTracks::updateInstallStatus($order_main_id, $data);

            $option['related_id'] = $order->id;
            app('notification')->send(
                $order->uid,
                Template::ORDER,
                Template::ORDER_TYPE[1],
                getNoticeTitle(1, "COMPLETED"),
                ['order_no' => $order->order_sn],
                $option
            );

            return true;
        } catch (\Exception $e) {
            throw new AppException("确认验收失败");
        }
    }

    /**
     * 获取用户订单地址列表
     */
    public function getAddress(): array
    {
        $member_id = \YunShop::app()->getMemberId();

        $orders = Order::where('uid', $member_id)
            ->where('parent_id', 0)
            ->where('order_type', 1)
            ->select('id', 'project_id')
            ->get();

        $orderIds = $orders->pluck('id')->toArray();
        $projectMap = $orders->pluck('project_id', 'id');

        $projectList = Project::whereIn('id', $projectMap->values()->unique())
            ->pluck('name', 'id');

        $addressList = OrderAddress::whereIn('order_main_id', $orderIds)
            ->select('order_main_id', 'address', 'mobile', 'realname', 'province_id', 'city_id', 'district_id')
            ->get()
            ->unique('address');

        $results = $addressList->map(function ($item) use ($projectMap, $projectList) {
            $address_map = explode(" ", $item->address);
            $projectId = $projectMap[$item->order_main_id] ?? null;
            return [
                'province' => $address_map[0] ?? '',
                'city' => $address_map[1] ?? '',
                'district' => $address_map[2] ?? '',
                'address' => implode(" ", array_slice($address_map, 3)),
                'phone' => $item->mobile,
                'realname' => $item->realname,
                'province_id' => $item->province_id,
                'city_id' => $item->city_id,
                'district_id' => $item->district_id,
                'project_name' => $projectList[$projectId] ?? '',
            ];
        });

        return $results->values()->toArray();
    }
}
