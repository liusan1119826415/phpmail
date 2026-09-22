<?php

namespace app\common\modules\pcnotice;

class Template
{

    const SYSTEM = "system";

    const ORDER = "order";

    const AUDIT = "audit";

    const TRANS = "transaction";


    const REPORT_NAME = "report";

    const BID_NAME = "bid";

    const FACTORY = "factory";


    const ORDER_TYPE = [
       1=>"purchase_order",// 采购订单
       2=>"bidding_order",// 标书订单
       3=>"deposit_order",// 项目保证金订单
       4=>"brand_usage_order",// 品牌使用费订单
       5=>"door_order"// 上门订单
    ];





    // 报备相关模板
    const REPORT = [
        'SUBMIT_SUCCESS' => 'report_submit_success', // 报备提交成功
        "SUBMIT_EDIT"    => 'report_edit_success',                      // 报备修改成功
        'APPROVE_PASS' => 'report_approve_pass',     // 报备审核通过
        'APPROVE_REJECT' => 'report_approve_reject',  // 报备审核驳回
        'APPROVE_REVOKE' => 'report_approve_revoke'  // 报备审核通过撤回

    ];

    // 投标相关模板
    const BID = [
        'SUBMIT_SUCCESS' => 'bid_submit_success',    // 投标提交成功
        "SUBMIT_EDIT"    => 'bid_edit_success', // 投标修改成功
        'APPROVE_PASS' => 'bid_approve_pass',        // 投标审核通过
        'APPROVE_REJECT' => 'bid_approve_reject',     // 投标审核驳回
        'APPROVE_REVOKE' => 'bid_approve_revoke'  // 投标审核通过撤回
    ];

    // 考察相关模板
    const INSPECTION = [
        'SUBMIT_SUCCESS' => 'inspection_submit_success',  // 考察提交成功
        'APPROVE_PASS' => 'inspection_approve_pass',      // 考察通过
        "SUBMIT_EDIT"    => 'inspection_edit_success', // 考察修改
        'APPROVE_REJECT' => 'inspection_approve_reject',   // 考察驳回
        'APPROVE_REVOKE' => 'inspection_approve_revoke'  // 考察审核通过撤回
    ];

    // 采购订单模板
    const PURCHASE_ORDER = [
        'SUBMIT' => 'purchase_order_submit',          // 提交订单
        'DRAWING_VIEW' => 'purchase_order_drawing_view', // 图纸查看
        'DEPOSIT_PAID' => 'purchase_order_deposit_paid', // 预付款支付
        'PRODUCTION_COMPLETE' => 'purchase_order_production_complete', // 生产完成
        'FINAL_PAYMENT' => 'purchase_order_final_payment', // 尾款支付
        'DELIVERY' => 'purchase_order_delivery',      // 商品发货
        'COMPLETED' => 'purchase_order_completed',     // 交易完成
        'CLOSE'=>'purchase_order_close'
    ];

    // 上门订单模板
    const SERVICE_ORDER = [
        'SUBMIT' => 'service_order_submit',          // 提交订单
        'ACCEPTED' => 'service_order_accepted',       // 接单完成
        'PAYMENT' => 'service_order_inspection_complete', // 验收完成
        'COMPLETED' => 'service_order_completed',      // 交易完成
        'CLOSE'=>'service_order_close'
    ];

    // 标书订单模板
    const BIDDING_ORDER = [
        'SUBMIT' => 'bidding_order_submit',          // 提交订单
        'PAYMENT' => 'bidding_order_payment',        // 付款通知
        'DELIVERY' => 'bidding_order_delivery',      // 发货通知
        'INSPECTION' => 'bidding_order_inspection',    // 验收通知
        'CLOSE'=>'bidding_order_close'
    ];


    // 品牌使用费订单模板
    const BRAND_USAGE_ORDER = [
        'SUBMIT' => 'brand_usage_order_submit',       // 提交申请
        'PAYMENT' => 'brand_usage_order_payment',     // 费用支付
        'FINISH' => 'brand_usage_order_finish', // 完成
        'CLOSE'=>'brand_usage_order_close'

    ];

// 项目保证金订单模板
    const PROJECT_DEPOSIT_ORDER = [
        'SUBMIT' => 'project_deposit_order_submit',   // 保证金申请
        'PAYMENT' => 'project_deposit_order_payment', // 保证金支付
        'FINISH' => 'project_deposit_order_finish',   // 完成
        'CLOSE'=>'project_deposit_order_close'

    ];

    // 发票模板
    const INVOICE = [
        'ISSUED' => 'invoice_issued'                 // 开票成功
    ];

    // 售后模板
    const AFTER_SALE = [
        'REFUND_APPROVED' => 'after_sale_refund_approved', // 同意退款
        'REFUND_COMPLETED' => 'after_sale_refund_completed' // 退款完成
    ];

    /**
     * 获取所有模板配置
     */
    public static function getAllTemplates(): array
    {
        return [
            // 报备模板配置
            self::REPORT['SUBMIT_SUCCESS'] => [
                'title' => '报备提交成功通知',
                'content' => '您的报备信息已成功提交，报备项目为：{{report_name}}',
                'variables' => ['report_name']
            ],
            self::REPORT['SUBMIT_EDIT'] => [
                'title' => '报备修改成功通知',
                'content' => '您的报备信息已修改成功并提交，报备项目为：{{report_name}}',
                'variables' => ['report_name']
            ],
            self::REPORT['APPROVE_PASS'] => [
                'title' => '报备审核通过通知',
                'content' => '您的报备项目（{{report_name}}）已审核通过',
                'variables' => ['report_name']
            ],
            self::REPORT['APPROVE_REJECT'] => [
                'title' => '报备审核驳回通知',
                'content' => '您的报备项目（{{report_name}}）未通过审核，原因：{{reason}}',
                'variables' => ['report_name', 'reason']
            ],

            //撤销审核
            self::REPORT['APPROVE_REVOKED']=>[
                'title' => '报备撤销审核通过通知',
                'content' => '您的报备项目（{{report_name}}）已被撤销审核通过',
                'variables' => ['report_name']
            ],

            // 投标模板配置
            self::BID['SUBMIT_SUCCESS'] => [
                'title' => '投标提交成功通知',
                'content' => '您的投标项目（{{bid_name}}）申请已成功提交',
                'variables' => ['bid_name']
            ],

            self::BID['SUBMIT_EDIT'] => [
                'title' => '投标修改成功通知',
                'content' => '您的投标信息已修改成功并提交，投标项目为：{{report_name}}',
                'variables' => ['bid_name']
            ],

            self::BID['APPROVE_PASS'] => [
                'title' => '投标审核通过通知',
                'content' => '您的投标项目（{{bid_name}}）申请已通过审核',
                'variables' => ['bid_name']
            ],
            self::BID['APPROVE_REJECT'] => [
                'title' => '投标审核驳回通知',
                'content' => '您的投标项目（{{bid_name}}）申请未通过审核，原因：{{reason}}',
                'variables' => ['bid_name', 'reason']
            ],


            //撤销审核
            self::BID['APPROVE_REVOKED']=>[
                'title' => '投标撤销审核通过通知',
                'content' => '您的投标项目（{{bid_name}}）已被撤销审核通过',
                'variables' => ['bid_name']
            ],

            // 考察模板配置
            self::INSPECTION['SUBMIT_SUCCESS'] => [
                'title' => '考察提交成功通知',
                'content' => '您的考察项目（{{inspection_name}}）申请已成功提交',
                'variables' => ['inspection_name']
            ],

            self::INSPECTION['SUBMIT_EDIT'] => [
                'title' => '考察修改成功通知',
                'content' => '您的考察项目（{{inspection_name}}）修改已成功提交',
                'variables' => ['inspection_name']
            ],
            self::INSPECTION['APPROVE_PASS'] => [
                'title' => '考察通过通知',
                'content' => '您的考察项目申请（{{inspection_name}}）已通过审核',
                'variables' => ['inspection_name']
            ],
            self::INSPECTION['APPROVE_REJECT'] => [
                'title' => '考察驳回通知',
                'content' => '您的考察项目申请（{{inspection_name}}）未通过审核，原因：{{reason}}',
                'variables' => ['inspection_name', 'reason']
            ],


            //撤销审核
            self::INSPECTION['APPROVE_REVOKED']=>[
                'title' => '工厂考察撤销审核通过通知',
                'content' => '您的考察项目（{{inspection_name}}）已被撤销审核通过',
                'variables' => ['inspection_name']
            ],

            // 采购订单模板配置
            self::PURCHASE_ORDER['SUBMIT'] => [
                'title' => '采购订单提交通知',
                'content' => '采购订单（{{order_no}}）已提交，金额：{{amount}}',
                'variables' => ['order_no', 'amount']
            ],
            self::PURCHASE_ORDER['DRAWING_VIEW'] => [
                'title' => '图纸查看通知',
                'content' => '订单（{{order_no}}）的图纸已上传，请及时查看',
                'variables' => ['order_no']
            ],
            self::PURCHASE_ORDER['DEPOSIT_PAID'] => [
                'title' => '预付款支付成功通知',
                'content' => '订单（{{order_no}}）预付款￥{{amount}}已支付成功',
                'variables' => ['order_no', 'amount']
            ],
            self::PURCHASE_ORDER['PRODUCTION_COMPLETE'] => [
                'title' => '生产完成通知',
                'content' => '订单（{{order_no}}）商品已生产完成',
                'variables' => ['order_no']
            ],
            self::PURCHASE_ORDER['FINAL_PAYMENT'] => [
                'title' => '尾款支付成功通知',
                'content' => '订单（{{order_no}}）尾款￥{{amount}}已支付成功',
                'variables' => ['order_no', 'amount']
            ],
            self::PURCHASE_ORDER['DELIVERY'] => [
                'title' => '商品发货通知',
                'content' => '订单（{{order_no}}）已发货，请前往订单详情查看物流信息',
                'variables' => ['order_no']
            ],
            self::PURCHASE_ORDER['COMPLETED'] => [
                'title' => '交易完成通知',
                'content' => '订单（{{order_no}}）已完成交易',
                'variables' => ['order_no']
            ],

            self::PURCHASE_ORDER['CLOSE'] => [
                'title' => '订单取消通知',
                'content' => '订单（{{order_no}}）已取消',
                'variables' => ['order_no']
            ],

            // 上门订单模板配置
            self::SERVICE_ORDER['SUBMIT'] => [
                'title' => '上门订单提交通知',
                'content' => '上门服务订单（{{order_no}}）已创建，预约时间：{{appointment_time}}',
                'variables' => ['order_no', 'appointment_time']
            ],
            self::SERVICE_ORDER['ACCEPTED'] => [
                'title' => '接单完成通知',
                'content' => '您的上门服务订单（{{order_no}}）已被接单，技术人员姓名：{{name}}，联系电话：{{mobile}}',
                'variables' => ['order_no', 'name','mobile']
            ],
            self::SERVICE_ORDER['PAYMENT'] => [
                'title' => '支付完成通知',
                'content' => '上门服务订单（{{order_no}}）已完成支付，支付金额为：{{amount}}',
                'variables' => ['order_no','amount']
            ],
            self::SERVICE_ORDER['COMPLETED'] => [
                'title' => '交易完成通知',
                'content' => '上门服务订单（{{order_no}}）已完成交易，验收完成',
                'variables' => ['order_no']
            ],




            self::SERVICE_ORDER['CLOSE'] => [
                'title' => '上门订单取消通知',
                'content' => '上门服务订单（{{order_no}}）已取消',
                'variables' => ['order_no']
            ],

            // 标书订单模板配置
            self::BIDDING_ORDER['SUBMIT'] => [
                'title' => '标书订单提交通知',
                'content' => '标书订单（{{order_no}}）已提交,订单金额为￥{{amount}}',
                'variables' => ['order_no','amount']
            ],
            self::BIDDING_ORDER['PAYMENT'] => [
                'title' => '标书订单付款通知',
                'content' => '标书订单（{{order_no}}）已支付金额：￥{{amount}}',
                'variables' => ['order_no', 'amount']
            ],
            self::BIDDING_ORDER['DELIVERY'] => [
                'title' => '标书订单发货通知',
                'content' => '标书订单（{{order_no}}）已发货，物流公司：{{express_name}}，快递单号：{{express_no}}',
                'variables' => ['order_no', 'express_name','express_no']
            ],
            self::BIDDING_ORDER['INSPECTION'] => [
                'title' => '标书验收通知',
                'content' => '标书订单（{{order_no}}）已验收完成',
                'variables' => ['order_no']
            ],


            self::BIDDING_ORDER['CLOSE'] => [
                'title' => '标书订单取消通知',
                'content' => '标书订单（{{order_no}}）已取消',
                'variables' => ['order_no']
            ],

            //品牌使用费
            self::BRAND_USAGE_ORDER['SUBMIT'] => [
                'title' => '品牌使用费订单提交通知',
                'content' => '品牌使用费订单（{{order_no}}）已提交，订单金额为￥{{amount}}',
                'variables' => ['order_no','amount']
            ],

            self::BRAND_USAGE_ORDER['PAYMENT'] => [
                'title' => '品牌使用费付款通知',
                'content' => '品牌使用费订单（{{order_no}}）已支付金额：￥{{amount}}付款成功',
                'variables' => ['order_no','amount']
            ],

            self::BRAND_USAGE_ORDER['FINISH'] => [
                'title' => '品牌使用费订单完成通知',
                'content' => '品牌使用费订单（{{order_no}}）已完成',
                'variables' => ['order_no']
            ],


            self::BRAND_USAGE_ORDER['CLOSE'] => [
                'title' => '品牌使用费订单取消通知',
                'content' => '品牌使用费订单（{{order_no}}）已取消',
                'variables' => ['order_no']
            ],





            //项目保证金
            self::PROJECT_DEPOSIT_ORDER['SUBMIT'] => [
                'title' => '项目保证金订单提交通知',
                'content' => '项目保证金订单（{{order_no}}）已提交，订单金额为￥{{amount}}',
                'variables' => ['order_no','amount']
            ],

            self::PROJECT_DEPOSIT_ORDER['PAYMENT'] => [
                'title' => '项目保证金付款通知',
                'content' => '项目保证金订单（{{order_no}}）已支付金额：￥{{amount}}付款成功',
                'variables' => ['order_no','amount']
            ],

            self::PROJECT_DEPOSIT_ORDER['FINISH'] => [
                'title' => '项目保证金订单完成通知',
                'content' => '项目保证金订单（{{order_no}}）已完成',
                'variables' => ['order_no']
            ],

            self::PROJECT_DEPOSIT_ORDER['CLOSE'] => [
                'title' => '项目保证金订单取消通知',
                'content' => '项目保证金订单（{{order_no}}）已取消',
                'variables' => ['order_no']
            ],


            // 发票模板配置
            self::INVOICE['ISSUED'] => [
                'title' => '开票成功通知',
                'content' => '发票（{{invoice_no}}）已开具，金额：{{amount}}',
                'variables' => ['invoice_no', 'amount']
            ],

            // 售后模板配置
            self::AFTER_SALE['REFUND_APPROVED'] => [
                'title' => '退款申请通过通知',
                'content' => '您的退款申请（{{refund_no}}）已通过审核',
                'variables' => ['refund_no']
            ],
            self::AFTER_SALE['REFUND_COMPLETED'] => [
                'title' => '退款完成通知',
                'content' => '退款申请（{{refund_no}}）已完成，金额：{{amount}}',
                'variables' => ['refund_no', 'amount']
            ]
        ];
    }

    /**
     * 获取指定模板配置
     */
    public static function getTemplate(string $templateKey): array
    {
        $templates = self::getAllTemplates();
        return $templates[$templateKey] ?? [
                'title' => '系统通知',
                'content' => '您有一条新的通知',
                'variables' => []
            ];
    }
}