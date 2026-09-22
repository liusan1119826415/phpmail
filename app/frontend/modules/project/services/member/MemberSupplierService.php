<?php

namespace app\frontend\modules\project\services\member;

use app\backend\modules\goods\models\ReturnAddress;
use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\helpers\Cache;
use app\common\models\Address;
use app\common\models\kefu\ServiceUser;
use app\common\models\project\PayAccount;
use app\frontend\modules\member\services\MemberService;
use Illuminate\Support\Carbon;
use Yunshop\Supplier\common\models\AccountVerify;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\WeiQingUsers;

class MemberSupplierService
{
    // 供应商状态
    const SUPPLIER_STATUS_APPROVED = 1;  // 审核通过
    const SUPPLIER_STATUS_REJECTED = 2;  // 审核拒绝
    const SUPPLIER_STATUS_INIT = 0;      // 初始

    // 申请状态
    const APPLY_STATUS_INIT = 1;
    const APPLY_STATUS_VERIFIED = 2;
    const APPLY_STATUS_ACCOUNT = 3;
    const APPLY_STATUS_SHOP = 4;

    // 支付状态
    const PAY_STATUS_PENDING = 0;
    const PAY_STATUS_VERIFIED = 2;
    const PAY_STATUS_FAILED = 3;

    // 子状态
    const SUB_STATUS_STEP1 = 2;
    const SUB_STATUS_STEP2 = 3;
    const SUB_STATUS_STEP3 = 1;

    // 验证限制
    const MAX_VERIFY_ATTEMPTS = 2;

    // 时间常量
    const SECONDS_24_HOURS = 86400;

    // 角色类型
    const ROLE_TYPE_NAME = '家具供应商';

    // 错误提示
    const MSG_ALREADY_APPROVED = '已经申请审核通过,无需重复申请！';
    const MSG_APPLY_FAILED = '申请失败';
    const MSG_EXCEPTION_FAILED = '异常失败';
    const MSG_PLEASE_STEP_ONE = '请完善第一步';
    const MSG_PLEASE_STEP_TWO = '请完善第二步';
    const MSG_PLEASE_PREVIOUS = '请完善前面的步骤';
    const MSG_VERIFY_LIMIT = '验证次数已达上限，请联系客服';
    const MSG_SUPPLIER_NOT_APPLIED = '供应商未申请';
    const MSG_SUPPLIER_NOT_APPROVED = '供应商状态未通过';
    const MSG_ACCOUNT_NOT_VERIFIED = '对公账户未验证通过';
    const MSG_ACCOUNT_EXISTS = '账号已经存在！';

    /**
     * 供应商申请第一步
     */
    public function applyStepOne(array $data): array
    {
        $check_code = MemberService::checkCode();
        if ($check_code['status'] != 1) {
            throw new AppException($check_code['json']);
        }
        try {
            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if ($supplier) {
                if ($supplier->status == self::SUPPLIER_STATUS_APPROVED) {
                    throw new AppException(self::MSG_ALREADY_APPROVED);
                }
                $model = $supplier;
            } else {
                $model = new Supplier;
                $data['uniacid'] = \YunShop::app()->uniacid;
                $data['member_id'] = \YunShop::app()->getMemberId();
            }
            $data['business_license'] = $data['certificate'];
            unset($data['certificate']);
            $data['status'] = self::SUPPLIER_STATUS_INIT;
            $data['sub_status'] = self::SUB_STATUS_STEP1;
            $data['apply_status'] = self::APPLY_STATUS_INIT;
            $data['apply_time'] = time();
            $model->setRawAttributes($data);

            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {
                throw new ShopException($validator->messages());
            } else {
                if ($model->save()) {
                    return ['id' => $model->id];
                } else {
                    throw new ShopException(self::MSG_APPLY_FAILED);
                }
            }
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_EXCEPTION_FAILED);
        }
    }

    /**
     * 供应商申请第二步
     */
    public function applyStepTwo(array $data): array
    {
        return app(\app\frontend\modules\project\infrastructure\BaseRepository::class)->transaction(function () use ($data) {
            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if (!$supplier) {
                throw new ShopException(self::MSG_PLEASE_STEP_ONE);
            }
            $data['sub_status'] = self::SUB_STATUS_STEP2;
            $supplier->setRawAttributes($data);

            $validator = $supplier->validator($supplier->getAttributes());
            if ($validator->fails()) {
                throw new ShopException($validator->messages());
            } else {
                if ($supplier->save()) {
                    return ['id' => $supplier->id];
                } else {
                    throw new ShopException(self::MSG_APPLY_FAILED);
                }
            }
        });
    }

    /**
     * 供应商申请第三步
     */
    public function applyStepThree(array $data): array
    {
        $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
        if (!$supplier) {
            throw new ShopException(self::MSG_PLEASE_STEP_TWO);
        }
        $data['sub_status'] = self::SUB_STATUS_STEP3;
        $data['apply_status'] = self::APPLY_STATUS_VERIFIED;
        $supplier->setRawAttributes($data);

        $validator = $supplier->validator($supplier->getAttributes());
        if ($validator->fails()) {
            throw new ShopException($validator->messages());
        } else {
            if ($supplier->save()) {
                return ['id' => $supplier->id];
            } else {
                throw new ShopException(self::MSG_APPLY_FAILED);
            }
        }
    }

    /**
     * 账户验证
     */
    public function applyAccountVerify(array $data): array
    {
        $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
        if (!$supplier) {
            throw new ShopException(self::MSG_PLEASE_PREVIOUS);
        }
        $supplier_id = $supplier->id;
        $data['sub_status'] = self::SUB_STATUS_STEP1;
        $data['apply_status'] = self::APPLY_STATUS_ACCOUNT;
        $data['apply_account_time'] = time();
        $supplier->setRawAttributes($data);

        $validator = $supplier->validator($supplier->getAttributes());
        if ($validator->fails()) {
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
                throw new ShopException(self::MSG_APPLY_FAILED);
            }
        }
    }

    /**
     * 对公账户金额验证
     */
    public function amountVerify(array $data): bool
    {
        $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
        $supplier_id = $supplier->id;
        if (!$supplier) {
            throw new ShopException(self::MSG_PLEASE_PREVIOUS);
        }

        if (!isset($supplier->verify_num)) {
            $supplier->verify_num = 0;
        }

        if ($supplier->verify_num >= self::MAX_VERIFY_ATTEMPTS) {
            throw new ShopException(self::MSG_VERIFY_LIMIT);
        }

        if ($supplier->amount != $data['amount']) {
            $supplier->verify_num += 1;
            $supplier->pay_status = self::PAY_STATUS_FAILED;
            $supplier->save();
            AccountVerify::where('status', self::PAY_STATUS_VERIFIED)
                ->where('supplier_id', $supplier_id)
                ->update(['status' => 3]);
            $remainingAttempts = self::MAX_VERIFY_ATTEMPTS - $supplier->verify_num;
            throw new ShopException("验证失败，剩余尝试次数：{$remainingAttempts}次");
        } else {
            $supplier->verify_num = 0;
            $supplier->pay_status = self::PAY_STATUS_VERIFIED;
            $supplier->sub_status = self::SUB_STATUS_STEP2;
            $supplier->pass_account_time = time();
            $supplier->save();
            AccountVerify::where('status', self::PAY_STATUS_VERIFIED)
                ->where('supplier_id', $supplier_id)
                ->update(['status' => 4]);
            return true;
        }
    }

    /**
     * 供应商申请
     */
    public function applySupplier(array $data): array
    {
        try {
            $supplier = \Yunshop\Supplier\admin\models\Supplier::where('member_id', \YunShop::app()->getMemberId())->first();
            if ($supplier) {
                if ($supplier->status == self::SUPPLIER_STATUS_REJECTED) {
                    throw new AppException(self::MSG_ALREADY_APPROVED);
                }
                $model = $supplier;
            } else {
                $model = new Supplier;
                $data['uniacid'] = \YunShop::app()->uniacid;
                $data['member_id'] = \YunShop::app()->getMemberId();
            }
            $data['status'] = self::SUPPLIER_STATUS_INIT;
            $data['apply_status'] = self::APPLY_STATUS_INIT;
            $data['apply_time'] = time();

            $supplier = Supplier::getSupplierByUsername($data['username']);
            $user = WeiQingUsers::getUserByUserName($data['username'])->first();

            if ($user || $supplier) {
                throw new AppException(self::MSG_ACCOUNT_EXISTS);
            }

            $model->setRawAttributes($data);
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {
                throw new ShopException($validator->messages());
            } else {
                if ($model->save()) {
                    event(new \app\common\events\plugin\SupplierEvent($model));
                    return ['id' => $model->id];
                } else {
                    throw new ShopException(self::MSG_APPLY_FAILED);
                }
            }
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_EXCEPTION_FAILED);
        }
    }

    /**
     * 获取申请状态
     */
    public function getApplyStatus(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $supplier = Supplier::where('member_id', $member_id)->first();

        if ($supplier) {
            $addressMap = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id])->pluck('areaname')->toArray();
            $pay_account = PayAccount::find(1);
            $is_hours24 = 0;

            if ($supplier->verify_num >= self::MAX_VERIFY_ATTEMPTS) {
                $account_verify = AccountVerify::where('member_id', $member_id)
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('status', [1, 3])
                    ->orderBy('created_at', 'desc')->first();
                $now = Carbon::now();
                $is_hours24 = 1;
                if (!$account_verify->created_at) {
                    $is_hours24 = 0;
                }
                if ($now->diffInSeconds($account_verify->created_at) >= self::SECONDS_24_HOURS) {
                    $is_hours24 = 0;
                    $supplier->verify_num = 0;
                    $supplier->save();
                }
                $remainingSeconds = self::SECONDS_24_HOURS - $now->diffInSeconds($account_verify->created_at);
            }

            $result = [
                'id' => $supplier->id,
                'status' => $supplier->status,
                'apply_status' => $supplier->apply_status,
                'sub_status' => $supplier->sub_status,
                'pay_status' => $supplier->pay_status,
                'company_name' => $supplier->company_name,
                'credit_code' => $supplier->credit_code,
                'province_id' => $supplier->province_id,
                'city_id' => $supplier->city_id,
                'district_id' => $supplier->district_id,
                'address' => $supplier->address,
                'company_address_detail' => implode(" ", [
                    $addressMap[0], $addressMap[1], $addressMap[2], $supplier->address,
                ]),
                'certificate' => yz_tomedia($supplier->business_license),
                'id_card_front' => yz_tomedia($supplier->id_card_front),
                'id_card_back' => yz_tomedia($supplier->id_card_back),
                'legal_person' => $supplier->legal_person,
                'id_card_no' => $supplier->id_card_no,
                'mobile' => $supplier->mobile,
                'username' => $supplier->username ?: "",
                'store_name' => $supplier->store_name ?: "",
                'role_type_name' => self::ROLE_TYPE_NAME,
                'apply_name' => $supplier->apply_name,
                'apply_phone' => $supplier->apply_phone,
                'apply_email' => $supplier->apply_email,
                'pay_account' => [
                    'account_name' => $pay_account->account_name,
                    'bank_card_no' => $pay_account->bank_card_no,
                ],
                'verify_num' => $supplier->verify_num,
                'bank_account' => $supplier->bank_account,
                'bank_username' => $supplier->bank_username,
                'bank_of_accounts' => $supplier->bank_of_accounts,
                'opening_branch' => $supplier->opening_branch,
                'apply_account_time' => $supplier->apply_account_time ? date("Y-m-d H:i:s", $supplier->apply_account_time) : "",
                'review_date' => $supplier->created_at->copy()->addDays(3)->format('Y-m-d'),
                'is_hours24' => $is_hours24,
                'hours24' => $is_hours24 == 1 ? ($remainingSeconds ?? 0) : 0,
                'service_link' => ServiceUser::getDistributeService(0),
                'pass_account_time' => $supplier->pass_account_time ? date("Y-m-d H:i:s", $supplier->pass_account_time) : "",
            ];

            if ($supplier->status == self::SUPPLIER_STATUS_REJECTED) {
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

    /**
     * 完善店铺
     */
    public function improveShop(array $data): bool
    {
        try {
            $member_id = \YunShop::app()->getMemberId();
            $supplier = Supplier::where('member_id', $member_id)->first();
            $supplier->apply_status = self::APPLY_STATUS_SHOP;
            $supplier->sub_status = self::SUB_STATUS_STEP3;
            $supplier->enable = 0;
            $supplier->save();
            if (!$supplier) {
                throw new AppException(self::MSG_SUPPLIER_NOT_APPLIED);
            }
            if ($supplier->status != self::SUPPLIER_STATUS_APPROVED) {
                throw new AppException(self::MSG_SUPPLIER_NOT_APPROVED);
            }
            if ($supplier->pay_status != self::PAY_STATUS_VERIFIED) {
                throw new AppException(self::MSG_ACCOUNT_NOT_VERIFIED);
            }
            $supplier_info = $supplier->toArray();

            $password = $data['password'];
            $uid = Supplier::addWeiqingTables($supplier_info['username'], $password);
            Supplier::where('member_id', $member_id)->update([
                'uid' => $uid,
                'apply_status' => self::APPLY_STATUS_SHOP,
            ]);
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : self::MSG_EXCEPTION_FAILED);
        }
    }

    /**
     * 保存退货地址
     */
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
        $addressModel = $ReturnAddress ?: new ReturnAddress();
        $addressModel->setRawAttributes($delivery_address);
        $validator = $addressModel->validator($addressModel->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($addressModel->save()) {
                return true;
            } else {
                throw new ShopException(MemberProfileService::MSG_INFO_IMPROVE_FAILED);
            }
        }
    }
}
