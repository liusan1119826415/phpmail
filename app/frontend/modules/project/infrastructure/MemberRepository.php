<?php

namespace app\frontend\modules\project\infrastructure;

use app\backend\modules\goods\models\ReturnAddress;
use app\common\exceptions\AppException;

use app\common\exceptions\ShopException;
use app\common\facades\EasyWeChat;
use app\common\helpers\Cache;
use app\common\models\AccountWechats;
use app\common\models\Address;
use app\common\models\industry\CaseFavorite;
use app\common\models\industry\CaseLable;
use app\common\models\kefu\ServiceUser;
use app\common\models\Member;
use app\common\models\project\Notification;
use app\common\services\Session;
use app\frontend\modules\member\models\MemberUniqueModel;
use app\frontend\modules\member\models\MemberWechatQrcodeModel;
use Illuminate\Support\Carbon;
use app\common\models\project\CompanyAuths;
use app\common\models\project\Industry;
use app\common\models\project\PayAccount;
use app\common\models\project\RealNameVerification;
use app\frontend\modules\member\services\MemberService;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\repositories\MemberRepositoryInterface;
use Illuminate\Support\Str;
use Yunshop\Supplier\common\models\AccountVerify;
use Yunshop\Supplier\common\models\IndustryCase;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierFollow;
use Yunshop\Supplier\common\models\WeiQingUsers;
use Illuminate\Support\Facades\Redis;

class MemberRepository extends BaseRepository implements MemberRepositoryInterface
{

    public function __construct(Project $model)
    {
        parent::__construct($model);
    }


    public function storeUserProfile(array $data): bool
    {
        try {
            $memberId = \YunShop::app()->getMemberId();
            $member = Member::where('uid', $memberId)->first();
            $member->avatar = $data['avatar'];
            $member->nickname = $data['nickname'];
            $member->industry_id = $data['industry_id'];
            $member->identity_id = $data['identity_id'];
            $member->province_id = $data['province_id'];
            $member->city_id = $data['city_id'];
            $member->save();
            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }

    public function getIndustry(int $id): array
    {
        $industry = Industry::select("id", "name")->where('parent_id', $id)->get()->toArray();
        return $industry;
    }


    public function getMemberInfo(): array
    {
        $id = \YunShop::app()->getMemberId();
        $member = Member::select("uid", "avatar", "mobile", 'industry_id', 'identity_id', 'nickname', 'province_id', 'city_id', 'account_type')->where('uid', $id)->first()->toArray();
        $MemberUniqueModel = MemberUniqueModel::where('member_id',$member['uid'])->first();
        $member['is_bind_wechat'] = $MemberUniqueModel->unionid?1:0;
        return $member;
    }

    public function updatePwd(): bool
    {
        try {
            $or_password = request()->or_password;

            $password = request()->password;
            $member_id = \YunShop::app()->getMemberId();
            $re_password = request()->re_password;

            $member = $member = \app\backend\modules\member\models\Member::uniacid()->where('uid', $member_id)->first();

            $or_password = md5($or_password . $member->salt);

            if ($member->password != $or_password) {
                throw new ShopException('原始密码错误');
            }

            if ($password != $re_password) {
                throw new ShopException('两次密码不一致');
            }
            if (strlen($password) < 6) {
                throw new ShopException('密码最少是6位数');
            }
            //随机数
            $data['salt'] = Str::random(8);
            //加密
            $data['password'] = md5($password . $data['salt']);
            $member->fill($data);
            $member->save();
            return true;
        } catch (\Exception $e) {
            throw new ShopException('修改密码失败', $e->getMessage());
        }
    }

    public function verifyRealName(array $data): bool
    {
        $check_code = MemberService::checkCode();
        if ($check_code['status'] != 1) {
            throw new AppException($check_code['json']);
        }
        $member_id = \YunShop::app()->getMemberId();

        $model = RealNameVerification::where('member_id', $member_id)->first();
        if (!$model) {
            $model = new RealNameVerification;
        }
        $data['member_id'] = \YunShop::app()->getMemberId();
        unset($data['code']);
        $model->setRawAttributes($data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($model->save()) {
                return true;
            } else {
                throw new AppException("实名申请失败");
            }
        }


    }

    public function getRealNameStatus(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $model = RealNameVerification::where('member_id', $member_id)->first();
        if (!$model) {
            $verify_status = -1;
        } else {
            $verify_status = $model->status;
        }

        $mobile = Member::where('uid', $member_id)->value('mobile');
        return [
            'verify_status' => $verify_status,
            'mobile' => $mobile
        ];
    }

    public function verifyCompany(array $data): bool
    {
//        $check_code = MemberService::checkCode();
//        if ($check_code['status'] != 1) {
//            throw new AppException($check_code['json']);
//        }
        $member_id = \YunShop::app()->getMemberId();

        $model = CompanyAuths::where('member_id', $member_id)->first();
        if (!$model) {
            $model = new CompanyAuths;
        }
        $data['member_id'] = \YunShop::app()->getMemberId();
        unset($data['code'], $data['mobile']);

        $model->setRawAttributes($data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($model->save()) {
                return true;
            } else {
                throw new AppException("企业申请失败");
            }
        }
    }


    public function getCompanyStatus(): array
    {

        $member_id = \YunShop::app()->getMemberId();
        $model = CompanyAuths::where('member_id', $member_id)->first();
        if (!$model) {
            $verify_status = -1;
        } else {
            $verify_status = $model->status;
        }

        $mobile = Member::where('uid', $member_id)->value('mobile');
        $data = [
            'verify_status' => $verify_status,
            'mobile' => $mobile,

        ];
        if ($model) {
            // $data['company_name'] = $this->getMasked($model->company_name,1);
            $data['company_name'] = $model->company_name;

            $data['credit_code'] = $this->getMasked($model->credit_code, 2);
            $data['legal_person'] = $this->getMasked($model->legal_person, 3);
            $data['license_image'] = yz_tomedia($model->license_image);
            $data['tax_number'] = $model->tax_number;
            $data['bank_name'] = $model->bank_name;
            $data['bank_account'] = $model->bank_account;
            $data['company_address'] = $model->company_address;
            $data['company_phone'] = $model->company_phone;
            $data['company_id'] = $model->id;
            if ($model->status == 1) {
                $data['Invoice_type'] = [
                    [
                        "id" => 1,
                        "name" => "普通发票"
                    ],
                    [
                        "id" => 2,
                        "name" => "专用发票"
                    ]
                ];
            } else {
                $data['Invoice_type'] = [
                    [
                        "id" => 1,
                        "name" => "普通发票"
                    ]
                ];
            }
        } else {
            $data['Invoice_type'] = [
                [
                    "id" => 1,
                    "name" => "普通发票"
                ]
            ];
        }

        return $data;
    }

    private function getMasked($name, $type)
    {
        if ($type == 1) {
            $length = mb_strlen($name);
            if ($length <= 2) return str_repeat('*', $length);
            return str_repeat('*', $length - 2) . mb_substr($name, -2);
        } elseif ($type == 2) {
            if (strlen($name) <= 8) return str_repeat('*', strlen($name));
            return substr($name, 0, 4) . str_repeat('*', strlen($name) - 8) . substr($name, -4);
        } elseif ($type == 3) {
            $length = mb_strlen($name);
            if ($length <= 1) return '*';
            return mb_substr($name, 0, 1) . str_repeat('*', $length - 1);
        }

    }

    //供应商申请第一步
    public function applyStepOne(array $data): array
    {
        $check_code = MemberService::checkCode();

        if ($check_code['status'] != 1) {
            throw new AppException($check_code['json']);
        }
        try {

            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if ($supplier) {
                if ($supplier->status == 1) {
                    throw new AppException('已经申请审核通过,无需重复申请！');
                }
                $model = $supplier;
            } else {
                $model = new Supplier;
                $data['uniacid'] = \YunShop::app()->uniacid;
                $data['member_id'] = \YunShop::app()->getMemberId();
            }
            $data['business_license'] = $data['certificate'];
            unset($data['certificate']);
            $data['status'] = 0;
            $data['sub_status'] = 2;
            $data['apply_status'] = 1;
            $data['apply_time'] = time();
            $model->setRawAttributes($data);

            //字段检测
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                if ($model->save()) {
                    return ['id' => $model->id];
                } else {
                    throw new ShopException("申请失败");
                }
            }
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }

    public function applyStepTwo(array $data): array
    {
       return $this->transaction(function () use ($data) {
            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if (!$supplier) {
                throw new ShopException("请完善第一步");
            }
            /*$delivery_address = $data['delivery_address'];
            $this->saveRefundAddress($delivery_address, $supplier);
            unset($data['delivery_address']);*/
            $data['sub_status'] = 3;
            $supplier->setRawAttributes($data);

            //字段检测
            $validator = $supplier->validator($supplier->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                if ($supplier->save()) {

                    return ['id' => $supplier->id];
                } else {
                    throw new ShopException("申请失败");
                }
            }
        });


    }

    public function applyStepThree(array $data): array
    {
        $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
        if (!$supplier) {
            throw new ShopException("请完善第二步");
        }
        $data['sub_status'] = 1;
        $data['apply_status'] = 2;
        $supplier->setRawAttributes($data);

        //字段检测
        $validator = $supplier->validator($supplier->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new ShopException($validator->messages());
        } else {
            if ($supplier->save()) {
                return ['id' => $supplier->id];
            } else {
                throw new ShopException("申请失败");
            }
        }

    }

    //账户验证

    public function applyAccountVerify(array $data):array
    {
        $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
        if (!$supplier) {
            throw new ShopException("请完善前面的步骤");
        }
        $supplier_id = $supplier->id;
        $data['sub_status'] = 2;
        $data['apply_status'] = 3;
        $data['apply_account_time'] = time();
        $supplier->setRawAttributes($data);

        //字段检测
        $validator = $supplier->validator($supplier->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new ShopException($validator->messages());
        } else {

            if ($supplier->save()) {
                $history['member_id'] = \YunShop::app()->getMemberId();
                $history['supplier_id'] = $supplier_id;
                $history['bank_account'] = $data['bank_account'];
                $history['bank_username'] = $data['bank_username'];
                $history['bank_of_accounts'] = $data['bank_of_accounts'];
                $history['opening_branch'] = $data['opening_branch'];
                AccountVerify::create($history);
                return ['id' => $supplier_id];
            } else {
                throw new ShopException("申请失败");
            }
        }

    }

    //对公账户金额验证
    public function amountVerify(array $data): bool
    {


            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            $supplier_id = $supplier->id;
            if (!$supplier) {
                throw new ShopException("请完善前面的步骤");
            }

            // 初始化验证次数（如果字段不存在）
            if (!isset($supplier->verify_num)) {
                $supplier->verify_num = 0;
            }

            // 检查是否超过最大验证次数（例如2次）
            $maxAttempts = 2;
            if ($supplier->verify_num >= $maxAttempts) {

                throw new ShopException("验证次数已达上限，请联系客服");
            }

            if ($supplier->amount != $data['amount']) {
                // 验证失败：增加失败次数并更新状态
                $supplier->verify_num += 1;
                $supplier->pay_status = 3; // 验证失败状态
                $supplier->save();
                AccountVerify::where('status', 2)->where('supplier_id', $supplier_id)->update(
                    [
                        'status' => 3
                    ]
                );
                $remainingAttempts = $maxAttempts - $supplier->verify_num;
                throw new ShopException("验证失败，剩余尝试次数：{$remainingAttempts}次");
            } else {
                // 验证成功：重置验证次数并更新状态
                $supplier->verify_num = 0; // 重置验证次数
                $supplier->pay_status = 2; // 验证成功状态
                $supplier->sub_status = 3;
                $supplier->pass_account_time = time();
                $supplier->save();
                //修改记录
                AccountVerify::where('status', 2)->where('supplier_id', $supplier_id)->update(
                    [
                        'status' => 4
                    ]
                );
                return true;
            }

    }


    public function applySupplier(array $data): array
    {

        try {
            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if ($supplier) {
                if ($supplier->status == 2) {
                    throw new AppException('已经申请审核通过,无需重复申请！');
                }
                $model = $supplier;
            } else {
                $model = new Supplier;
                $data['uniacid'] = \YunShop::app()->uniacid;
                $data['member_id'] = \YunShop::app()->getMemberId();
            }
            $data['status'] = 0;
            $data['apply_status'] = 1;

            $data['apply_time'] = time();

            $supplier = Supplier::getSupplierByUsername($data['username']);
            $user = WeiQingUsers::getUserByUserName($data['username'])->first();
            //$has_wait_apply = Supplier::uniacid()->selectRaw('1')->where('member_id',$data['member_id'])->first();
            /*if (Redis::get('supplier_apply_delay' . $data['member_id']) || $has_wait_apply) {
                throw new AppException('请勿重复提交！');
            }*/
            if ($user || $supplier) {
                throw new AppException('账号已经存在！');
            }

            $model->setRawAttributes($data);

            //字段检测
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new ShopException($validator->messages());
            } else {
                if ($model->save()) {
                    event(new \app\common\events\plugin\SupplierEvent($model));
                    // Redis::setex('supplier_apply_delay' . $data['member_id'], 5, 1);
                    return ['id' => $model->id];
                } else {
                    throw new ShopException("申请失败");
                }
            }
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }




    public function getApplyStatus(): array
    {
        $hour24 = 86400;
        $member_id = \YunShop::app()->getMemberId();
        $supplier = Supplier::where('member_id', $member_id)->first();

        if ($supplier) {
            $addressMap = Address::whereIn('id',[$supplier->province_id,$supplier->city_id,$supplier->district_id])->pluck('areaname')->toArray();
            $pay_account = PayAccount::find(1);
            //获取倒计时验证
            $is_hours24 = 0;

            if($supplier->verify_num>=2){
                $account_verify = AccountVerify::where('member_id',$member_id)->where('supplier_id',$supplier->id)->whereIn('status',[1,3])->orderBy('created_at','desc')->first();
                $now = Carbon::now();
                $is_hours24 = 1;
                if(!$account_verify->created_at){
                    $is_hours24 = 0;

                }
                if($now->diffInSeconds($account_verify->created_at) >= $hour24){
                    $is_hours24 = 0;
                    $supplier->verify_num = 0;
                    $supplier->save();
                }
                $remainingSeconds = $hour24 - $now->diffInSeconds($account_verify->created_at);

            }
            $result = [
                'id' => $supplier->id,
                'status' => $supplier->status,
                'apply_status' => $supplier->apply_status,
                'sub_status' => $supplier->sub_status,
                'pay_status'=>$supplier->pay_status,
                'company_name' => $supplier->company_name,
                'credit_code' => $supplier->credit_code,
                'province_id' => $supplier->province_id,
                'city_id' => $supplier->city_id,
                'district_id' => $supplier->district_id,
                'address' => $supplier->address,
                'company_address_detail'=>implode(" ",[
                    $addressMap[0],
                    $addressMap[1],
                    $addressMap[2],
                    $supplier->address
                ]),
                'certificate' => yz_tomedia($supplier->business_license),
                'id_card_front' => yz_tomedia($supplier->id_card_front),
                'id_card_back' => yz_tomedia($supplier->id_card_back),
                'legal_person' => $supplier->legal_person,
                'id_card_no' => $supplier->id_card_no,
                'mobile' => $supplier->mobile,
                //第二步
                'username' => $supplier->username?:"",
                'store_name' => $supplier->store_name?:"",
               // 'logo' => yz_tomedia($supplier->logo),
                'role_type_name' => "家具供应商",

                'apply_name' => $supplier->apply_name,
                'apply_phone' => $supplier->apply_phone,
                'apply_email' => $supplier->apply_email,
                'pay_account'=>[
                  'account_name'=>$pay_account->account_name,
                  'bank_card_no'=>$pay_account->bank_card_no,
                ],
                //银行信息
                'verify_num'=>$supplier->verify_num,
                'bank_account' => $supplier->bank_account,
                'bank_username' => $supplier->bank_username,
                'bank_of_accounts' => $supplier->bank_of_accounts,
                'opening_branch' => $supplier->opening_branch,
                'apply_account_time'=>$supplier->apply_account_time?date("Y-m-d H:i:s",$supplier->apply_account_time):"",
                'review_date' => $supplier->created_at->copy()->addDays(3)->format('Y-m-d'),
                'is_hours24'=>$is_hours24,
                'hours24'=>$is_hours24 ==1?$remainingSeconds:0,
                "service_link"=>ServiceUser::getDistributeService(0),
                "pass_account_time"=>$supplier->pass_account_time?date("Y-m-d H:i:s",$supplier->pass_account_time):"",

            ];
           /* $ReturnAddress = ReturnAddress::where('is_refund', 2)->where('supplier_id', $supplier->id)->where('is_default', 1)->first();
            if($ReturnAddress){
                $result['delivery_address'] = [
                    'province_id' => $ReturnAddress->province_id ?: "",
                    'city_id' => $ReturnAddress->city_id ?: "",
                    'district_id' => $ReturnAddress->district_id ?: "",
                    'address' => $ReturnAddress->address ?: "",
                    'address_detail'=>implode(" ",[
                        $ReturnAddress->province_name,
                        $ReturnAddress->city_name,
                        $ReturnAddress->district_name,
                        $ReturnAddress->address
                    ])
                ];
            }*/

            //付款方信息


            if ($supplier->status == 2) {
                $result['reject_reason'] = $supplier->reject_reason;
            }
        } else {
            $result = [
                'status' => -1,
                'apply_status' => 0,
            ];
        }

        return $result;
    }


    public function improveShop(array $data): bool
    {

        try {
            $member_id = \YunShop::app()->getMemberId();
            $supplier = Supplier::where('member_id', $member_id)->first();
            $supplier->apply_status = 4;
            $supplier->sub_status = 1;
            $supplier->enable = 0;  //等设置好了再上线
            $supplier->save();
            if (!$supplier) {
                throw new AppException("供应商未申请");
            }
            if ($supplier->status != 1) {
                throw new AppException("供应商状态未通过");
            }
            if($supplier->pay_status != 2){
                throw new AppException("对公账户未验证通过");
            }
            $supplier_info = $supplier->toArray();

            $password = $data['password'];

                            //设置密码

            $uid = Supplier::addWeiqingTables($supplier_info['username'], $password);
            Supplier::where('member_id', $member_id)->update(
                [
                    "uid" => $uid,
                    "apply_status" => 4
                ]
            );
            return true;

        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }


    }

    public function getFollowSupplier($name): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = SupplierFollow::where('member_id', $member_id);
        if ($name) {
            $query->whereHas('supplier', function ($query) use ($name) {
                $query->where('store_name', 'like', '%' . $name . '%');
            });
        }

        $data = $query->with(['goods' => function ($query) {
            $query->select("id", "supp_id", "title", "price", "thumb")->limit(5);
        }, 'supplier' => function ($query) {
            $query->select("id", "store_name", "logo");
        }])->paginate(self::PAGE_SIZE);
        $data->transform(function ($item) {
            // 计算关注时间距今几天
            $item->follow_days = $item->created_at->diffInDays(now());
            $item->supplier->logo = yz_tomedia($item->supplier->logo);
            // 处理每个商品的 thumb 图片地址
            $item->goods->transform(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb); // 或你项目中自定义的图片处理函数
                return $goods;
            });

            return $item;
        });

        return $data->toArray();

    }

    public function getFollowCase(array $search): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $query = CaseFavorite::where('member_id', $memberId);
        if ($search['case_id']) {
            $query->whereHas('industryCase', function ($query) use ($search) {
                $query->whereIn('case_id', $search['case_id']);
            });
        }

        if ($search['name']) {
            $query->whereHas('industryCase', function ($query) use ($search) {
                $query->where('title', '%' . $search['name'] . '%');
            });
        }

        $data = $query->with(['industryCase' => function ($query) {
            $query->with(['brandCase' => function ($query) {
                $query->select("id", "store_name", "logo");
            }]);
        }])->paginate(self::PAGE_SIZE);

        $data->transform(function ($item) {

            $item->industryCase->thumb = yz_tomedia($item->industryCase->thumb);
            $item->industryCase->brandCase->logo = yz_tomedia($item->industryCase->brandCase->logo);
            return $item;
        });

        return $data->toArray();


    }

    //获取用户已经关注过的类型
    public function getFollowCaseLable(): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $caseIds = CaseFavorite::where('member_id', $memberId)->pluck('case_id')->toArray();
        $ids = IndustryCase::whereIn('id', $caseIds)->pluck('case_id')->toArray();
        $data = CaseLable::select("id", "name")->whereIn('id', $ids)->get()->toArray();
        return $data;
    }


    protected function saveRefundAddress($delivery_address, $supplier_info)
    {

        $ReturnAddress = ReturnAddress::where('is_refund', 2)->where('supplier_id', $supplier_info->id)->where('is_default', 1)->first();
        $addressMap = \app\common\models\member\Address::whereIn('id', [$delivery_address['province_id'], $delivery_address['city_id'], $delivery_address['district_id'], $delivery_address['street_id']])->pluck('areaname')->toArray();
        $delivery_address['province_name'] = $addressMap[0] ?: "";
        $delivery_address['city_name'] = $addressMap[1] ?: "";
        $delivery_address['district_name'] = $addressMap[2] ?: "";
        $delivery_address['street_name'] = $addressMap[3] ?: "";
        $delivery_address['supplier_id'] = $supplier_info->id;
        $delivery_address['uniacid'] = \YunShop::app()->uniacid;
        $delivery_address['is_refund'] = 2;
        $delivery_address['is_default'] = 1;
        $delivery_address['supplier_id'] = $supplier_info['id'];
        $delivery_address['address_name'] = $supplier_info['store_name'];
        $delivery_address['contact'] = $supplier_info['realname'];
        $delivery_address['mobile'] = $supplier_info['mobile'];
        $delivery_address['address'] = $delivery_address['address'];
        if ($ReturnAddress) {
            $addressModel = $ReturnAddress;
        } else {
            $addressModel = new ReturnAddress();
        }
        $addressModel->setRawAttributes($delivery_address);
        $validator = $addressModel->validator($addressModel->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            if ($addressModel->save()) {

                return true;
            } else {
                throw new ShopException("完善信息失败");
            }
        }

    }



    public function generateQrCode():array
    {

        if (!is_null(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'))) {
            $class    = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'class');
            $function = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'function');
            $config = $class::$function();
        }
        $memberId = \YunShop::app()->getMemberId();

        $MemberUniqueModel = MemberUniqueModel::where('member_id',$memberId)->first();
        if($MemberUniqueModel){
            throw new AppException('账号已经绑定微信');
        }
        $uniacid  = \YunShop::app()->uniacid;
        $callback = ($_SERVER['REQUEST_SCHEME'] ? $_SERVER['REQUEST_SCHEME'] : 'http')  . '://' . $_SERVER['HTTP_HOST']."/addons/yun_shop/api.php?i={$uniacid}&type=5&route=project.member.handleCallback";
        $state = Str::random(32); // 用于校验防伪

        Cache::put("wechat_bind_state:$state", $memberId, now()->addMinutes(30));

        //return "https://open.weixin.qq.com/connect/qrconnect?appid=" . $appId ."&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_login&state={$state}#wechat_redirect";
        $url = sprintf(
            "https://open.weixin.qq.com/connect/qrconnect?appid=%s&redirect_uri=%s&response_type=code&scope=snsapi_login&state=%s#wechat_redirect",
            $config['appid'],
            urlencode($callback),
            $state
        );

        return [
            'qr_code_url' => $url,
            'state' => $state
        ];




    }

    public function handleCallback()
    {

        try {
        \Log::debug("=====handleCallback====",request()->input());
        if (!is_null(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'))) {
            $class    = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'class');
            $function = array_get(\app\common\modules\shop\ShopConfig::current()->get('wechat_qrcode_config'), 'function');
            $config = $class::$function();
        }
        $state = request()->state;
        $member_id = Cache::get("wechat_bind_state:$state");
        if (!$member_id) {

            Cache::put("wechat_bind_result:$state", [
                'status' => 'fail',
                'message' => '非法请求'
            ], 300);
            throw new AppException('非法请求');
        }


        $uniacid  = \YunShop::app()->uniacid;
        $code = request()->code;
        if (!$code) {
            throw new AppException('Authorization code missing');
        }
        $token = $this->_getTokenUrl($config['appid'], $config['app_secret'], $code);

        if (!empty($token) && is_array($token) && $token['errmsg'] == 'invalid code') {
            echo "invalid code";die;
        }

        $user_info = $this->_getUserInfoUrl($token['access_token'], $token['openid']);
        $MemberUniqueModel = MemberUniqueModel::where('unionid',$user_info['unionid'])->first();
        $member = Member::where('uid', $member_id)->first();
        if($MemberUniqueModel){

            echo '该微信已绑定其他账号'.$member->nickname;die;
        }
        MemberUniqueModel::replace(array(
            'uniacid' => $uniacid,
            'unionid' => $user_info['unionid'],
            'member_id' => $member_id,
            'type' => 5
        ));



        MemberWechatQrcodeModel::replace(array(
            'uniacid'   => $uniacid,
            'member_id' => $member_id,
            'openid'    => $user_info['openid'],
            'nickname'  => $user_info['nickname'],
            'avatar'    => $user_info['headimgurl'],
            'gender'    => $user_info['sex'],
            'province'  => '',
            'country'   => '',
            'city'      => '',
        ));

            $member->nickname = $user_info['nickname'];
            $member->avatar = $user_info['headimgurl'];
            $member->gender = $user_info['sex'];
            $member->save();
            $result = [
                'success' => true,
                'message' => '微信绑定成功'
            ];

        } catch (\Exception $e) {
            // 如果过程中出现任何异常（AppException或其他）
            \Log::error("微信绑定回调出错: " . $e->getMessage());
            $result = [
                'success' => false,
                'message' => $e->getMessage() // 或使用一个更友好的错误提示，如 '绑定失败，请重试'
            ];
        }
        return $result;

    }



    private function _getTokenUrl($appId, $appSecret, $code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appId . "&secret=" . $appSecret . "&code=" . $code . "&grant_type=authorization_code";
        return $tokenurl = \Curl::to($url)
            ->asJsonResponse(true)
            ->get();
    }

    private function _getUserInfoUrl($accesstoken, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$accesstoken}&openid={$openid}&lang=zh_CN";
        return $userinfo_url = \Curl::to($url)
            ->asJsonResponse(true)
            ->get();
    }
    private function getQrCodeUrl()
    {
        $WE_CHAT_SHOW_QR_CODE_URL = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=";
        return $WE_CHAT_SHOW_QR_CODE_URL . $this->getTicket();
    }
    private function getTicket()
    {
        return $this->createQR()['ticket'];
    }
    private function createQR()
    {
        $account =  AccountWechats::getAccountByUniacid(\YunShop::app()->uniacid);
        $options = [
            'app_id'  => $account->key,
            'secret'  => $account->secret,
        ];
        $app = EasyWeChat::officialAccount($options);
        $qrcode = $app->qrcode;
        $result = $qrcode->temporary($this->getSceneValue(), 120);
        return $result;
    }


    /**
     * 获取唯一场景值
     * @return string
     */
    private function getSceneValue()
    {
       /* $memberId = \YunShop::app()->getMemberId();
        $scene = 'bind_' . $memberId . '_' . Str::random(8);
        $result = Redis::get($scene);
        if(!$result){
            Redis::setex($scene, 120, 0); //0 = 生成二维码未扫码
            return $scene;
        }else{
            $this->getSceneValue();
        }*/
        $scene = sha1(rand(0,999999));
        $result = Redis::get($scene);
        if(!$result){
            Redis::setex($scene, 120, 0); //0 = 生成二维码未扫码
            $this->scene = $scene;
            return $scene;
        }else{
            $this->getSceneValue();
        }


    }


    public function getNotifice(array $search):array
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = Notification::where('member_id',$member_id);
        $search['notice_type'] = $search['notice_type']?:"system";
        if($search['notice_type']){
            $query->where('notice_type',$search['notice_type']);
        }

        $list = $query->orderBy('created_at', 'desc')
            ->paginate(15);



        return ['list'=>$list];

    }


    /**
     * 获取未读消息数量统计
     * @return array
     */
    public function getUnRead(): array
    {
        $member_id = \YunShop::app()->getMemberId();

        // 获取各类型未读消息数量
        $unreadCounts = Notification::where('member_id', $member_id)
            ->where('is_read', 0)
            ->groupBy('notice_type')
            ->selectRaw('notice_type, count(*) as count')
            ->pluck('count', 'notice_type')
            ->toArray();

        // 计算总数
        $total = array_sum($unreadCounts);

        // 确保所有指定类型都有返回值，不存在则为0
        $result = [
            'total' => $total,
            'system' => $unreadCounts['system'] ?? 0,
            'order' => $unreadCounts['order'] ?? 0,
            'audit' => $unreadCounts['audit'] ?? 0,
            'transaction' => $unreadCounts['transaction'] ?? 0,
        ];

        return $result;
    }


    /**
     * 设置消息为已读
     * @param array $params 请求参数
     * @return array
     */
    public function setRead(array $params): array
    {
        $member_id = \YunShop::app()->getMemberId();

        // 验证参数
        if (empty($params['type'])) {
            return ['status' => 0, 'message' => '缺少必要参数: type'];
        }

        $query = Notification::where('member_id', $member_id)
            ->where('is_read', 0); // 只操作未读消息

        // 根据不同类型处理
        switch ($params['type']) {
            case 'single': // 单条消息设为已读
                if (empty($params['id'])) {
                    throw new AppException("缺少消息ID");
                }
                $query->where('id', $params['id']);
                break;

            case 'batch': // 批量设置已读
                if (empty($params['ids'])) {
                    throw new AppException("缺少消息ID列表");
                }
                $query->whereIn('id', (array)$params['ids']);
                break;

            case 'type': // 按类型设置全部已读
                if (empty($params['notice_type'])) {
                    throw new AppException("缺少消息类型");
                }
                $query->where('notice_type', $params['notice_type']);
                break;

            case 'all': // 全部设为已读
                // 不需要额外条件
                break;

            default:
                throw new AppException("不支持的操作类型");

        }

        // 执行更新
        $updated = $query->update(['is_read' => 1, 'read_at' => time()]);

        return [
            'data' => [
                'updated_count' => $updated
            ]
        ];
    }




}