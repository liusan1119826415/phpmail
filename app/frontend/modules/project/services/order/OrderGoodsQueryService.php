<?php

namespace app\frontend\modules\project\services\order;

use app\common\exceptions\ShopException;
use app\common\models\kefu\ServiceUser;
use app\frontend\models\OrderGoods;
use app\common\models\Order;

/**
 * 订单商品查询服务
 * 负责：订单商品列表（含楼层/空间分组）、生产进度、图纸列表
 */
class OrderGoodsQueryService
{
    /**
     * 获取订单商品（带缓存入口）
     */
    public function getOrderGoods(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $cacheKey = "order_goods_list_{$member_id}_{$search['order_id']}";
        return $this->getGoods($search);
    }

    /**
     * 核心商品查询（按楼层/空间分组）
     */
    protected function getGoods(array $search): array
    {
        $query = \app\common\models\OrderGoods::with([
            'order' => function ($query) {
                $query->select("id", "order_sn", "supp_id")->with(['supplier' => function ($query) {
                    $query->select("id", "store_name", "logo");
                }]);
            },
            'goodsOption' => function ($query) {
                $query->select("id", "length", "width", "height", "structure", 'mirror_enable');
            },
            'orderGoodsChildrens' => function ($query) {
                $query->select("*")
                    ->with(['goodsOption' => function ($query) {
                        $query->select("id", "length", "width", "height", "structure", 'mirror_enable');
                    }]);
            }
        ])
            ->leftJoin('yz_project_floors AS floors', 'yz_order_goods.floor_id', '=', 'floors.id')
            ->leftJoin('yz_project_floor_space AS floor_space', 'yz_order_goods.space_id', '=', 'floor_space.id')
            ->where('refund_success', 0);

        if ($search['order_id']) {
            $query->where('yz_order_goods.order_id', $search['order_id']);
        }

        if ($search['order_main_id']) {
            $order_ids = Order::where('parent_id', $search['order_main_id'])->pluck('id')->toArray();
            $query->whereIn('yz_order_goods.order_id', $order_ids);
        }

        if ($search['project_id']) {
            $query->where('yz_order_goods.project_id', $search['project_id'])
                ->whereHas('order', function ($query) {
                    $query->where('status', '!=', -1);
                });
        }

        if (isset($search['upload_status']) && ($search['upload_status'] !== '' || $search['upload_status'] === 0)) {
            $query->where('yz_order_goods.upload_status', $search['upload_status']);
        }

        $query->orderBy('floors.sort', 'asc')
            ->orderBy('floor_space.sort', 'asc')
            ->orderBy('yz_order_goods.id', 'asc');

        $baseRepo = app(\app\frontend\modules\project\infrastructure\BaseRepository::class);

        $OrderGoods = $query->get([
            'yz_order_goods.*',
            'floors.id as _floor_id',
            'floors.name as _floor_name',
            'floors.sort as _floor_sort',
            'floor_space.id as _space_id',
            'floor_space.name as _space_name',
            'floor_space.sort as _space_sort'
        ])->map(function ($item) use ($baseRepo) {
            $childrenData = [];
            if ($item->orderGoodsChildrens && $item->orderGoodsChildrens->isNotEmpty()) {
                foreach ($item->orderGoodsChildrens as $child) {
                    $childrenData[] = [
                        'id' => $child->id,
                        'title' => $child->title,
                        'product_price' => $child->product_price,
                        'total_price' => $child->total_price,
                        'upload_status' => $child->upload_status,
                        'length' => $child->goodsOption->length ?? null,
                        'width' => $child->goodsOption->width ?? null,
                        'height' => $child->goodsOption->height ?? null,
                        'structure' => $child->goodsOption->structure ?? null,
                        'goods_option_title' => $child->goods_option_title,
                        'is_mirrored' => $child->is_mirrored,
                        'mirror_enable' => $child->goodsOption->mirror_enable ?? 0,
                        'service_link' => ServiceUser::getDistributeService($child->goods_option_id, $child->goods_id, $child->goodsOption->mirror_enable ?? 0, $child->is_mirrored),
                        'store_name' => $item->order->supplier->store_name ?? '',
                        'logo' => yz_tomedia($item->order->supplier->logo ?? ''),
                        'spec_title' => $baseRepo->getSpecTitle([
                            'goods_option' => [
                                'length' => $child->goodsOption->length ?? null,
                                'width' => $child->goodsOption->width ?? null,
                                'height' => $child->goodsOption->height ?? null,
                                'mirror_enable' => $child->goodsOption->mirror_enable ?? 0
                            ],
                            'is_mirrored' => $child->is_mirrored,
                        ]),
                        'material_color' => $baseRepo->getMaterialColor($child->toArray()),
                        'draw_file' => $child->draw_file ? array_map(function ($value) {
                            return yz_tomedia($value);
                        }, unserialize($child->draw_file)) : [],
                        'upload_time' => $child->upload_time ? date("Y-m-d H:i:s", $child->upload_time) : "",
                        'confirm_time' => $child->confirm_time ? date("Y-m-d H:i:s", $child->confirm_time) : "",
                    ];
                }
            }

            $item->floor_id = $item->_floor_id ?? 0;
            $item->floor_name = $item->_floor_name ?? '未分配楼层';
            $item->space_id = $item->_space_id ?? 0;
            $item->space_name = $item->_space_name ?? '未分配空间';

            $item->service_link = ServiceUser::getDistributeService($item->goods_option_id, $item->goods_id, $item->goodsOption->mirror_enable ?? 0, $item->is_mirrored);
            $item->structure = $item->goodsOption->structure ?? null;
            $item->store_name = $item->order->supplier->store_name ?? '';
            $item->logo = yz_tomedia($item->order->supplier->logo ?? '');
            $item->material_color = $baseRepo->getMaterialColor($item->toArray());
            $item->draw_file = $item->draw_file ? array_map(function ($value) {
                return yz_tomedia($value);
            }, unserialize($item->draw_file)) : [];
            $item->upload_time = $item->upload_time ? date("Y-m-d H:i:s", $item->upload_time) : "";
            $item->confirm_time = $item->confirm_time ? date("Y-m-d H:i:s", $item->confirm_time) : "";
            $item->option_children = $childrenData;
            $item->is_mirrored = $item->is_mirrored;
            $item->mirror_enable = $item->goodsOption->mirror_enable ?? 0;
            $item->spec_title = $baseRepo->getSpecTitle([
                'goods_option' => [
                    'length' => $item->goodsOption->length ?? null,
                    'width' => $item->goodsOption->width ?? null,
                    'height' => $item->goodsOption->height ?? null,
                    'mirror_enable' => $item->goodsOption->mirror_enable ?? 0
                ],
                'is_mirrored' => $item->is_mirrored,
            ]);

            unset($item->_floor_id, $item->_floor_name, $item->_floor_sort, $item->_space_id, $item->_space_name, $item->_space_sort);

            return $item;
        });

        // 按楼层和空间分组
        $groupedData = [];
        foreach ($OrderGoods as $item) {
            $floorKey = $item->floor_id . '|' . $item->floor_name;
            $spaceKey = $item->space_id . '|' . $item->space_name;

            if (!isset($groupedData[$floorKey])) {
                $groupedData[$floorKey] = ['id' => $item->floor_id, 'name' => $item->floor_name, 'data' => []];
            }
            if (!isset($groupedData[$floorKey]['data'][$spaceKey])) {
                $groupedData[$floorKey]['data'][$spaceKey] = ['id' => $item->space_id, 'name' => $item->space_name, 'data' => []];
            }
            $groupedData[$floorKey]['data'][$spaceKey]['data'][] = $item;
        }

        return array_values(array_map(function ($floor) {
            $floor['data'] = array_values(array_map(function ($space) {
                $space['data'] = array_values($space['data']);
                return $space;
            }, $floor['data']));
            return $floor;
        }, $groupedData));
    }

    /**
     * 查看生产进度
     */
    public function getProductSchedule(int $project_id): array
    {
        $order_main_id = request()->order_main_id;
        $baseRepo = app(\app\frontend\modules\project\infrastructure\BaseRepository::class);

        $query = OrderGoods::select("id", "order_id", "title", "goods_option_title", "type", "goods_option_id", "product_sn", "goods_id", "thumb", "total", "components");
        if ($project_id) {
            $query->where('project_id', $project_id);
        }
        if ($order_main_id) {
            $query->where('order_main_id', $order_main_id);
        }

        $orderGoods = $query->with(['hasOneGoods' => function ($query) {
            $query->select("id", "lead_time", "supp_id")
                ->with(['supplierGoods' => function ($query) {
                    $query->select("id", "store_name", "logo");
                }]);
        }, 'goodsOption' => function ($query) {
            $query->select("id", "length", "width", "height");
        }])->get();

        return $orderGoods->groupBy(fn($item) => $item->hasOneGoods->supplierGoods->id ?? 0)
            ->map(function ($items, $supp_id) use ($baseRepo) {
                $max_lead_time = $items->max(fn($item) => $item->hasOneGoods->lead_time ?? 0);
                $pay_time = optional($items->first()->order)->first_pay_time;
                $ship_date = $pay_time ? date('Y-m-d', strtotime($pay_time . " +{$max_lead_time} days")) : null;
                $remaining_days = $ship_date ? max(0, ceil((strtotime($ship_date) - time()) / 86400)) : null;

                $order_id = optional($items->first())->order_id;
                $order = Order::find($order_id);

                $product_status = 0;
                if ($order && $order->status >= 1 && $order->orderStatus >= 2) {
                    $product_status = 1;
                }
                if ($order && $order->status == 1 && $order->orderStatus == 1) {
                    $product_status = 2;
                }

                $componentData = $items->toArray() ? $items->toArray()[0] : [];

                return [
                    'id' => $supp_id,
                    'store_name' => $items->first()->hasOneGoods->supplierGoods->store_name ?? '',
                    'logo' => yz_tomedia($items->first()->hasOneGoods->supplierGoods->logo ?? ''),
                    'service_link' => ServiceUser::getDistributeService($items->first()->goods_option_id, $items->first()->goods_id, $items->first()->goodsOption->mirror_enable, $items->first()->is_mirrored),
                    'estimated_ship_date' => $ship_date,
                    'remaining_days' => $remaining_days,
                    'material_color' => $baseRepo->getMaterialColor($componentData),
                    'product_status' => $product_status,
                    'data' => $items->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * 获取图纸列表
     */
    public function getDrawList(array $search): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $baseRepo = app(\app\frontend\modules\project\infrastructure\BaseRepository::class);

        $query = app('CartContainer')->make('MemberCart')->floor()
            ->where(app('CartContainer')->make('MemberCart')->getTable() . '.member_id', $memberId);
        $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.project_id', $search['project_id']);

        if ($search['floor_id']) {
            $query = $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.floor_id', $search['floor_id']);
        }
        if ($search['upload_status']) {
            $query = $query->where(app('CartContainer')->make('MemberCart')->getTable() . '.upload_status', $search['upload_status']);
        }

        $data = $query->orderBy(app('CartContainer')->make('MemberCart')->getTable() . '.created_at', 'desc')
            ->get()->toArray();

        $result = collect($data)->groupBy('belongs_to_floor.id')->map(function ($floorItems, $floorId) use ($baseRepo) {
            $floorName = $floorItems->first()['belongs_to_floor']['floor_name'];

            $spaces = collect($floorItems)->groupBy('belongs_to_space.id')->map(function ($spaceItems, $spaceId) use ($baseRepo) {
                $spaceName = $spaceItems->first()['belongs_to_space']['space_name'];

                $goodsData = $spaceItems->map(function ($item) use ($baseRepo) {
                    return [
                        "id" => $item['id'],
                        'goods_id' => $item['goods']['id'],
                        'title' => $item['goods']['title'],
                        'upload_status' => $item['upload_status'],
                        "upload_time" => !empty($item['upload_time']) ? date("Y-m-d H:i:s", $item['upload_time']) : "",
                        "confirm_time" => !empty($item['confirm_time']) ? date("Y-m-d H:i:s", $item['confirm_time']) : "",
                        "total" => $item['total'],
                        "customer_url" => "https://kefu.abangmi.com/im/kefu/index/index",
                        "store_name" => $item['goods']['supplierGoods']['store_name'],
                        "thumb" => yz_tomedia($item['goods_option']['thumb']),
                        "product_price" => $item['goods_option']['product_price'],
                        "unit_price" => $item['goods_option']['market_price'],
                        "total_price" => $item['total'] * $item['goods_option']['market_price'],
                        "status" => $baseRepo->getGoodsStatus($item),
                        "sku" => $item['goods']['sku'],
                        "type" => $item['type'],
                        "length" => $item['goods_option']['length'],
                        "width" => $item['goods_option']['width'],
                        "height" => $item['goods_option']['height'],
                        "product_model" => $item['goods_option']['product_model'],
                        "material_color" => $baseRepo->getMaterialColor($item),
                        "structure" => $item['goods']['structure'],
                        "volume" => $item['goods_option']['volume'],
                        "jsonData" => !empty($item['jsonData']) ? json_decode($item['jsonData'], true) : [],
                        "modelData" => !empty($item['modelData']) ? json_decode($item['modelData'], true) : [],
                    ];
                })->values();

                return [
                    'space_id' => $spaceId,
                    'space_name' => $spaceName,
                    'goods' => $goodsData,
                    'space_total_price' => $goodsData->sum('total_price'),
                    'space_total_num' => $goodsData->sum('total'),
                ];
            })->values();

            return [
                'floor_id' => $floorId,
                'floor_name' => $floorName,
                'space' => $spaces,
                'floor_total_price' => $spaces->sum('space_total_price'),
                'floor_total_num' => $spaces->sum('space_total_num'),
            ];
        })->values();

        return [
            'project_total_price' => $result->sum('floor_total_price'),
            'project_total_num' => $result->sum('floor_total_num'),
            'data' => $result->toArray()
        ];
    }
}
