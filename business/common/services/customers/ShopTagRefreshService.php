<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/9/22
 * Time: 13:37
 */


namespace business\common\services\customers;


use app\framework\Http\Request;
use business\common\job\ConnectCustomerTagMemberToShopJob;
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

class ShopTagRefreshService
{

    public $shop_tag_list;
    public $shop_tag_group;
//    public $customer_tag_list;
    public $business_id;
//    public $connect_shop_tag;
//    public $connect_shop_tag_group;
//    public $connect_shop_disabled; //禁止同步商城
    public $connect_wechat;

    use DispatchesJobs;

    public function __construct($business_id)
    {
        $this->business_id = $business_id ?: SettingService::bindBusinessId();
        $this->connect_wechat = $this->isConnectWechat();
    }


    public function refreshShopTag($is_dispatch = 1)
    {
        try {
            if (!SettingService::bindBusinessId($this->business_id)) {
                throw new Exception('非平台绑定企业无法同步商城标签');
            }

            $this->refreshShopGroup();
            $this->getShopTagList();
            if ($this->shop_tag_list->isNotEmpty()) {
                $this->shop_tag_list->each(function ($v) {
                    $this->changeTag($v);
                });
            }

            if ($is_dispatch) {
                $customer_member_ids = CustomerMember::uniacid()->usable()->business($this->business_id)->select('id', 'uid')->where('uid', '<>', 0)->groupBy('uid')->pluck('uid')->toArray();
                if (count($customer_member_ids) > 500) {
                    $customer_member_ids = array_chunk($customer_member_ids, 500);
                    foreach ($customer_member_ids as $v) {
                        $this->dispatch(new PushMemberTagJob(\YunShop::app()->uniacid, $this->business_id, $v));
                    }
                } else {
                    $this->connectShopTagMemberToWechat();
                }
            } else {
                $this->connectShopTagMemberToWechat();
            }

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return $this->returnArr(1, '同步商城标签成功');
    }


    /*
     * 根据企业客户标签同步商城会员标签
     */
    public function connectCustomerTagMemberToShop($group_ids = [], $tag_ids = [], $customer_member_ids = [])
    {
        $business_id = $this->business_id;
        try {
            $where = [['shop_tag_id', '<>', 0]];
            if ($group_ids) {
                $where[] = [function ($query) use ($group_ids) {
                    $query->whereIn('group_id', $group_ids);
                }];
            }
            if ($tag_ids) {
                $where[] = [function ($query) use ($tag_ids) {
                    $query->whereIn('tag_id', $tag_ids);
                }];
            }
            $customer_tag_id_list = CustomerTag::uniacid()->business($this->business_id)->where($where)->select('tag_id', 'shop_tag_id')->get();

            $customer_member_where = [['uid', '<>', 0]];
            if ($customer_member_ids) {
                $customer_member_where[] = [function ($query) use ($customer_member_ids) {
                    $query->whereIn('id', $customer_member_ids);
                }];
            }
            $customer_list = CustomerMember::uniacid()->usable()->business($this->business_id)->select('uid', 'tag_id')->where($customer_member_where)->get();
            $customer_uid_list = $customer_list->pluck('uid')->toArray();
            if (!$customer_uid_list) {
                return ['result' => 1, 'msg' => '成功', 'data' => []];
            }
            $chunk_list = $customer_tag_id_list->chunk(50);
            $chunk_list->each(function ($v) use (&$customer_uid_list, $business_id) {
                $this->dispatch(new ConnectCustomerTagMemberToShopJob(\YunShop::app()->uniacid, $business_id, $v, $customer_uid_list));
            });
//            $insert_data = [];
//            $base_data = [
//                'uniacid' => \YunShop::app()->uniacid,
//                'created_at' => time(),
//                'updated_at' => time(),
//            ];
//            $member_where = [];
//            $tag_where = [];
//            $customer_tag_id_list->each(function ($v) use ($base_data, &$insert_data, &$customer_uid_list, $business_id, &$member_where, &$tag_where) {
//                $shop_tag_uid = ShopTagMember::uniacid()->whereIn('member_id', $customer_uid_list)->where('tag_id', $v->shop_tag_id)->pluck('member_id')->toArray();
//                $customer_tag_uid = CustomerMember::getCustomerUidByTagId($v->tag_id, $business_id);
//                $shop_tag_uid = array_values(array_unique($shop_tag_uid));
//                $customer_tag_uid = array_values(array_unique($customer_tag_uid));
//                if ($add_uids = array_diff($customer_tag_uid, $shop_tag_uid)) {
//                    foreach ($add_uids as $add_uid) {
//                        $member_where[] = $add_uid;
//                        $tag_where[] = $v->shop_tag_id;
//                        $insert_data[$add_uid . '_' . $v->shop_tag_id] = array_merge($base_data, [
//                            'member_id' => $add_uid,
//                            'tag_id' => $v->shop_tag_id,
//                        ]);
//                    }
//                }
//                if ($delete_uids = array_diff($shop_tag_uid, $customer_tag_uid)) {
//                    ShopTagMember::where('member_id', $delete_uids)->where('tag_id', $v->shop_tag_id)->delete();
//                }
//            });
//
//            $insert_data = array_chunk(array_values($insert_data), 2000);
//            foreach ($insert_data as &$v) {
//                ShopTagMember::insert($v);
//            }
//
//            if ($member_where && $tag_where) { //去除批量插入时差可能导致的重复
//                $table_name = app('db')->getTablePrefix() . 'yz_member_tags';
//                $member_where = implode(',', array_values(array_unique($member_where)));
//                $tag_where = implode(',', array_values(array_unique($tag_where)));
//                DB::select("DELETE FROM $table_name WHERE (member_id,tag_id) IN (SELECT member_id,tag_id FROM (SELECT member_id,tag_id FROM $table_name WHERE member_id IN ($member_where) AND tag_id IN ($tag_where) GROUP BY member_id,tag_id HAVING COUNT(*)>1) s1) AND id NOT IN (SELECT id FROM (SELECT id FROM $table_name WHERE member_id IN ($member_where) AND tag_id IN ($tag_where) GROUP BY member_id,tag_id HAVING COUNT(*)>1) s2)");
//            }

        } catch (Exception $e) {
            return ['result' => 0, 'msg' => $e->getMessage(), 'data' => []];
        }
        return ['result' => 1, 'msg' => '成功', 'data' => []];
    }


    /*
     * 根据商城会员标签同步企业客户标签
     */
    public function connectShopTagMemberToWechat($uid = [])
    {
        $customer_list = [];
        $customer_tag_id_list = CustomerTag::uniacid()->business($this->business_id)->where('shop_tag_id', '<>', 0)->select('tag_id', 'shop_tag_id')->get();
        if ($uid) {
            $customer_member_arr = CustomerMember::uniacid()->usable()->business($this->business_id)->select('id', 'uid')->whereIn('uid', $uid)->get();
        } else {
            $customer_member_arr = CustomerMember::uniacid()->usable()->business($this->business_id)->select('id', 'uid')->where('uid', '<>', 0)->get();
        }
        $customer_member_list = [];
        $customer_member_arr->each(function ($v) use (&$customer_member_list) {
            $customer_member_list[$v->uid][] = $v->id;
        });
        $customer_uid_list = $customer_member_arr->pluck('uid')->toArray();
        $business_id = $this->business_id;
        $customer_tag_id_list->each(function ($v) use (&$customer_list, &$customer_uid_list, &$customer_member_list, $business_id) {
            $shop_tag_uid = ShopTagMember::uniacid()->whereIn('member_id', $customer_uid_list)->where('tag_id', $v->shop_tag_id)->pluck('member_id')->toArray();
            if ($customer_tag_uid = CustomerMember::getCustomerUidByTagId($v->tag_id, $business_id, $customer_uid_list)) {
                $not_like_customer_tag_uid = CustomerMember::business($this->business_id)->whereIn('uid', $customer_tag_uid)->where('tag_id', 'not like', '%' . $v->tag_id)->select('uid')->groupBy('uid')->pluck('uid')->toArray();
                $customer_tag_uid = array_diff($customer_tag_uid, $not_like_customer_tag_uid);
            }
            if ($add_uids = array_diff($shop_tag_uid, $customer_tag_uid)) {
                foreach ($add_uids as $add_uid) {
                    foreach ($customer_member_list[$add_uid] as $add_customer_id) {
                        $customer_list[$add_customer_id]['add_tag'][] = $v->tag_id;
                    }
                }
            }
            if ($delete_uids = array_diff($customer_tag_uid, $shop_tag_uid)) {
                foreach ($delete_uids as $delete_uid) {
                    foreach ($customer_member_list[$delete_uid] as $delete_customer_id) {
                        $customer_list[$delete_customer_id]['remove_tag'][] = $v->tag_id;
                    }
                }
            }
        });

        if ($customer_list) {
            (new CustomerTagRefreshService($this->business_id))->pushMemberTagToWechat($customer_list);
        }
    }

    /*
     * 更新企业客户标签组
     */
    public function refreshShopGroup()
    {
        $this->getShopTagGroup();
        if ($this->shop_tag_group->isNotEmpty()) {
            $this->shop_tag_group->each(function ($v) {
                if (!$customer_tag = CustomerTag::uniacid()->business($this->business_id)->where('shop_tag_group_id', $v->id)->first()) {
                    return;
                }
                $data = new Request();
                $data->offsetSet('id', $customer_tag->id);
                $data->offsetSet('name', $v->title);
                $data->offsetSet('order', $v->sort);
                $res = (new TagManageService($this->business_id))->editTagGroup($data);
                if (!$res['result']) {
                    throw new Exception('更新企业客户标签组失败');
                }
            });
        }
    }

    /*
   * 同步商城标签
   */
    public function changeTag($shop_tag)
    {
        $customer_tag = CustomerTag::uniacid()->where('shop_tag_id', $shop_tag->id)->first();
        if (!$customer_tag) {
            $this->addTag($shop_tag);
        } elseif ($customer_tag->shop_tag_group_id != $shop_tag->group_id) {
            $this->moveTag($customer_tag, $shop_tag);
        } else {
            $this->editTag($customer_tag, $shop_tag);
        }
    }


    /*
     * 创建标签
     */
    public function addTag($shop_tag)
    {
        if ($shop_tag->hasOneGroup) {
            $shop_group = $shop_tag->hasOneGroup;
        } else {
            $shop_tag->group_id = 0;
            $shop_tag = $this->handleShopTag($shop_tag);
            $shop_group = MemberTagGroup::uniacid()->find($shop_tag->group_id);
        }
        $data = new Request();
        $data->offsetSet('group_name', $shop_group->title);
        $data->offsetSet('order', $shop_group->sort ?: 0);
        $data->offsetSet('name_arr', [$shop_tag->title]);
        $res = (new TagManageService($this->business_id))->addTag($data);
        if (!$res['result']) {
            throw new Exception('创建企业微信标签失败:未知原因');
        }
    }

    /*
     * 编辑标签
     */
    public function editTag($customer_tag, $shop_tag)
    {
        $data = new Request();
        $data->offsetSet('id', $customer_tag->id);
        $data->offsetSet('name', $shop_tag->title);
        $data->offsetSet('order', $customer_tag->order);
        $res = (new TagManageService($this->business_id))->editTag($data);
        if (!$res['result']) {
            throw new Exception('编辑企业微信标签失败:未知原因');
        }
    }

    /*
     * 处理无分组的商城标签
     */
    public function handleShopTag($shop_tag)
    {
        $group_id = \Yunshop\WorkWechatTag\services\SettingService::getDefaultGroupId($this->business_id);
        $shop_group_id = CustomerTag::getShopTagGroupId($group_id, $this->business_id);
        if (!$shop_group_id) {
            throw new  Exception('获取商城标签组失败');
        }
        $shop_tag->group_id = $shop_group_id;
        $shop_tag->save();
        return $shop_tag;
    }


    /*
   * 移动标签
   */
    public function moveTag($customer_tag, $shop_tag)
    {
        $customer_tag->shop_tag_id = 0;
        $customer_tag->save();
        $customer_ids = TagMemberService::getCustomerIdByTagId($customer_tag->tag_id);
        $data = new Request();
        $data->offsetSet('id', $customer_tag->id);
        $res = (new TagManageService($this->business_id))->deleteTag($data);
        if (!$res['result']) {
            throw new Exception('移动标签组失败:未知原因');
        }
        $shop_group = MemberTagsGroupModel::uniacid()->find($shop_tag->group_id);
        $data = new Request();
        $data->offsetSet('group_name', $shop_group->title);
        $data->offsetSet('sort', $shop_group->sort ?: 0);
        $data->offsetSet('name_arr', [$shop_tag->title]);
        $res = (new TagManageService($this->business_id, 1))->addTag($data);
        if (!$res['result']) {
            throw new Exception('移动标签失败:未知原因');
        }
        $tag_id = $res['data']['tag_group']['tag'][0]['id'];
        $new_customer_tag = CustomerTag::uniacid()->business($this->business_id)->where('tag_id', $tag_id)->first();
        $new_customer_tag->shop_tag_id = $shop_tag->id;
        $new_customer_tag->shop_tag_group_id = $shop_tag->group_id;
        $new_customer_tag->save();
        (new CustomerTagRefreshService($this->business_id))->pushMemberTagToWechat($customer_ids, [$new_customer_tag->tag_id]);
    }

    public function isConnectWechat()
    {
        if (!app('plugins')->isEnabled('work-wechat-tag')) {
            $this->connect_wechat = false;
        } else {
            $this->connect_wechat = \Yunshop\WorkWechatTag\services\SettingService::isConnectWechat($this->business_id);
        }
        return $this->connect_wechat;
    }


    /*
     * 获取商城标签组列表
     */
    public function getShopTagGroup()
    {
        $this->shop_tag_group = MemberTagGroup::uniacid()->get();
    }

    /*
     * 获取商城标签列表
     */
    public function getShopTagList()
    {
//        \Yunshop\WorkWechatTag\services\SettingService::getDefaultGroupId();
        $this->shop_tag_list = ShopTag::uniacid()->select('id', 'group_id', 'title')->with(['hasOneGroup' => function ($query) {
            $query->select('id', 'title', 'sort');
        }])->get();
    }


    private function returnArr($result, $msg = '', $data = [])
    {
        return ['result' => $result, 'msg' => $msg, 'data' => $data];
    }


}