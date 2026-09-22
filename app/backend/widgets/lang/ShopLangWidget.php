<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 03/03/2017
 * Time: 12:19
 */

namespace app\backend\widgets\lang;



class ShopLangWidget
{
    public function getData()
    {
        return [
            'member_center' => [
                'tab' => '商城',
                'data' => [
                    [
                        'key' => 'credit',
                        'default' => '余额',
                        'remark' => ' "余额" 字样 自定义名称'
                    ],
                    [
                        'key' => 'credit1',
                        'default' => '积分',
                        'remark' => '"积分" 字样 自定义名称'
                    ]
                ]
            ],
            'income' => [
                'tab' => '提现收入设置',
                'data' => [
                    [
                        'key' => 'name_of_withdrawal',
                        'default' => '提现',
                        'remark' => ''
                    ],
                    [
                        'key' => 'income_name',
                        'default' => '收入',
                        'remark' => ''
                    ],
                    [
                        'key' => 'poundage_name',
                        'default' => '手续费',
                        'remark' => ''
                    ],
                    [
                        'key' => 'special_service_tax',
                        'default' => '劳务税',
                        'remark' => ''
                    ],
                    [
                        'key' => 'manual_withdrawal',
                        'default' => '手动提现',
                        'remark' => ''
                    ]
                ]
            ],
            'goods' => [
                'tab' => '商品',
                'data' => [
                    [
                        'key' => 'market_price',
                        'default' => '',
                        'name' => '原价',
                        'remark' => ''
                    ],
                    [
                        'key' => 'price',
                        'default' => '',
                        'name' => '现价',
                        'remark' => ''
                    ],
                    [
                        'key' => 'vip_price',
                        'default' => '会员价',
                        'remark' => '会员价自定义名称仅对首页商品、商品分类、商品列表页面起效'
                    ],
                    [
                        'key' => 'goods_profit',
                        'default' => '利润',
                        'remark' => ''
                    ],
                    [
                        'key' => 'buy_now',
                        'default' => '立即购买',
                        'remark' => '默认显示立即购买'
                    ],
                    [
                        'key' => 'cost_price',
                        'default' => '成本价',
                        'remark' => '成本价'
                    ]
                ]
            ],
            'agent' => [
                'tab' => '客户',
                'data' => [
                    [
                        'key' => 'agent',
                        'default' => '客户',
                        'remark' => ''
                    ],
                    [
                        'key' => 'agent_num',
                        'default' => '客户数量',
                        'remark' => ''
                    ],
                    [
                        'key' => 'agent_count',
                        'default' => '总客户数',
                        'remark' => ''
                    ],
                    [
                        'key' => 'agent_order',
                        'default' => '客户订单',
                        'remark' => ''
                    ],
                    [
                        'key' => 'agent_order_count',
                        'default' => '客户总订单',
                        'remark' => ''
                    ],
                    [
                        'key' => 'agent_goods_num',
                        'default' => '客户总支付商品数',
                        'remark' => ''
                    ],
                    [
                        'key' => 'share_member_name',
                        'default' => '推荐人',
                        'remark' => ''
                    ]
                ]
            ],
            'order' => [
                'tab' => '订单',
                'data' => [
                    [
                        'key' => 'received_goods',
                        'default' => '确认收货',
                        'remark' => ''
                    ],
                    [
                        'key' => 'deduction_lang',
                        'default' => '抵扣',
                        'remark' => '预下单页 "抵扣" 自定义名称'
                    ],
                    [
                        'key' => 'express',
                        'default' => '快递',
                        'remark' => '只控制自营商品和门店商品的快递字样'
                    ]
                ]
            ],


        ];
    }
}
