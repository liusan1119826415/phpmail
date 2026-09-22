<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */

namespace business\common\job;



use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use app\framework\Http\Request;
use business\common\job\PushMemberTagJob;
use business\common\services\customers\WechatCustomerRequestService;
use business\common\services\SettingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Yunshop\MemberTags\Common\models\MemberTagsGroupModel;
use Yunshop\MemberTags\Common\models\MemberTagsGroupModel as MemberTagGroup;
use Yunshop\WechatCustomers\common\models\CustomerFollowUser as CustomerMember;
use Yunshop\WechatCustomers\common\models\CustomerFollowUser as TagCustomer;
use Yunshop\MemberTags\Common\models\MemberTagsModel as ShopTag;
use Yunshop\MemberTags\Common\models\MemberTagsRelationModel as ShopTagMember;
use Yunshop\WechatCustomers\common\models\CustomerFollowUserTag as CustomerTag;
use Yunshop\WorkWechatTag\job\ChangeWechatMemberTagJob;
use Yunshop\WorkWechatTag\services\TagManageService;
use Yunshop\WorkWechatTag\services\TagMemberService;
use Illuminate\Foundation\Bus\DispatchesJobs;
use app\common\facades\Setting;


class ConnectCustomerTagMemberToShopJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;
    protected $business_id;
    public $uniacid;
    protected $customer_tag_id_list;
    protected $customer_uid_list;

    public function __construct($uniacid, $business_id,$customer_tag_id_list,$customer_uid_list)
    {
        $this->uniacid = $uniacid;
        $this->business_id = $business_id;
        $this->customer_tag_id_list = $customer_tag_id_list;
        $this->customer_uid_list = $customer_uid_list;
    }

    public function handle(){
        \Log::debug('企微标签同步商城标签job开始');
        \YunShop::app()->uniacid = $this->uniacid;
        Setting::$uniqueAccountId = $this->uniacid;
        $business_id = $this->business_id;
        $customer_tag_id_list = $this->customer_tag_id_list;
        $customer_uid_list = $this->customer_uid_list;
        $member_where = [];
        $tag_where = [];
        $insert_data = [];
        $base_data = [
            'uniacid' => \YunShop::app()->uniacid,
            'created_at' => time(),
            'updated_at' => time(),
        ];
        if(empty($customer_tag_id_list)){
            return;
        }
        $customer_tag_id_list->each(function ($v) use ($base_data, &$insert_data, &$customer_uid_list, $business_id, &$member_where, &$tag_where) {
            $shop_tag_uid = ShopTagMember::uniacid()->whereIn('member_id', $customer_uid_list)->where('tag_id', $v->shop_tag_id)->pluck('member_id')->toArray();
            $customer_tag_uid = CustomerMember::getCustomerUidByTagId($v->tag_id, $business_id);
            $shop_tag_uid = array_values(array_unique($shop_tag_uid));
            $customer_tag_uid = array_values(array_unique($customer_tag_uid));
            if ($add_uids = array_diff($customer_tag_uid, $shop_tag_uid)) {
                foreach ($add_uids as $add_uid) {
                    $member_where[] = $add_uid;
                    $tag_where[] = $v->shop_tag_id;
                    $insert_data[$add_uid . '_' . $v->shop_tag_id] = array_merge($base_data, [
                        'member_id' => $add_uid,
                        'tag_id' => $v->shop_tag_id,
                    ]);
                }
            }
            if ($delete_uids = array_diff($shop_tag_uid, $customer_tag_uid)) {
                ShopTagMember::where('member_id', $delete_uids)->where('tag_id', $v->shop_tag_id)->delete();
            }
        });

        $insert_data = array_chunk(array_values($insert_data), 2000);
        foreach ($insert_data as &$v) {
            ShopTagMember::insert($v);
        }

        if ($member_where && $tag_where) { //去除批量插入时差可能导致的重复
            $table_name = app('db')->getTablePrefix() . 'yz_member_tags';
            $member_where = implode(',', array_values(array_unique($member_where)));
            $tag_where = implode(',', array_values(array_unique($tag_where)));
            DB::select("DELETE FROM $table_name WHERE (member_id,tag_id) IN (SELECT member_id,tag_id FROM (SELECT member_id,tag_id FROM $table_name WHERE member_id IN ($member_where) AND tag_id IN ($tag_where) GROUP BY member_id,tag_id HAVING COUNT(*)>1) s1) AND id NOT IN (SELECT id FROM (SELECT id FROM $table_name WHERE member_id IN ($member_where) AND tag_id IN ($tag_where) GROUP BY member_id,tag_id HAVING COUNT(*)>1) s2)");
        }
        \Log::debug('企微标签同步商城标签job结束');
    }




}
