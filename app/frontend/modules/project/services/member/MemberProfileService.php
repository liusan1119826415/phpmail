<?php

namespace app\frontend\modules\project\services\member;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\industry\CaseFavorite;
use app\common\models\industry\CaseLable;
use app\common\models\Member;
use app\common\models\project\CompanyAuths;
use app\common\models\project\Industry;
use app\common\models\project\Notification;
use app\common\models\project\RealNameVerification;
use app\frontend\modules\member\models\MemberUniqueModel;
use app\frontend\modules\member\services\MemberService;
use Illuminate\Support\Str;
use Yunshop\Supplier\common\models\IndustryCase;
use Yunshop\Supplier\common\models\SupplierFollow;

class MemberProfileService
{
    // 认证状态
    const VERIFY_STATUS_NOT_VERIFIED = -1;  // 未认证
    const VERIFY_STATUS_PENDING = 0;        // 待审核
    const VERIFY_STATUS_APPROVED = 1;       // 已通过
    const VERIFY_STATUS_REJECTED = 2;       // 已拒绝

    // 密码最小长度
    const PASSWORD_MIN_LENGTH = 6;

    // 发票类型
    const INVOICE_TYPE_NORMAL = 1;  // 普通发票
    const INVOICE_TYPE_SPECIAL = 2; // 专用发票

    const INVOICE_TYPE_NAMES = [
        self::INVOICE_TYPE_NORMAL => '普通发票',
        self::INVOICE_TYPE_SPECIAL => '专用发票',
    ];

    // 企业审核通过状态
    const COMPANY_STATUS_APPROVED = 1;

    // 通知类型
    const DEFAULT_NOTICE_TYPE = 'system';

    // 消息已读操作类型
    const READ_ACTION_SINGLE = 'single';
    const READ_ACTION_BATCH = 'batch';
    const READ_ACTION_TYPE = 'type';
    const READ_ACTION_ALL = 'all';

    // 分页大小
    const NOTIFICATION_PAGE_SIZE = 15;

    // 错误提示
    const MSG_OPERATION_FAILED = '操作失败';
    const MSG_ORIGINAL_PWD_ERROR = '原始密码错误';
    const MSG_PWD_NOT_SET = '该账号尚未设置密码，请直接设置新密码';
    const MSG_PWD_MISMATCH = '两次密码不一致';
    const MSG_PWD_TOO_SHORT = '密码最少是6位数';
    const MSG_UPDATE_PWD_FAILED = '修改密码失败';
    const MSG_REALNAME_APPLY_FAILED = '实名申请失败';
    const MSG_COMPANY_APPLY_FAILED = '企业申请失败';
    const MSG_MISSING_TYPE = '缺少必要参数: type';
    const MSG_MISSING_MSG_ID = '缺少消息ID';
    const MSG_MISSING_MSG_IDS = '缺少消息ID列表';
    const MSG_MISSING_NOTICE_TYPE = '缺少消息类型';
    const MSG_UNSUPPORTED_ACTION = '不支持的操作类型';
    const MSG_INFO_IMPROVE_FAILED = '完善信息失败';

    /**
     * 保存用户资料
     */
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
            throw new ShopException(config("app.debug") ? $e->getMessage() : self::MSG_OPERATION_FAILED);
        }
    }

    /**
     * 获取行业
     */
    public function getIndustry(int $id): array
    {
        return Industry::select("id", "name")->where('parent_id', $id)->get()->toArray();
    }

    /**
     * 获取会员信息
     */
    public function getMemberInfo(): array
    {
        $id = \YunShop::app()->getMemberId();
        $member = Member::select("uid", "avatar", "mobile", 'industry_id', 'identity_id', 'nickname', 'province_id', 'city_id', 'account_type')
            ->where('uid', $id)->first()->toArray();
        $MemberUniqueModel = MemberUniqueModel::where('member_id', $member['uid'])->first();
        $member['is_bind_wechat'] = $MemberUniqueModel->unionid ? 1 : 0;
        return $member;
    }

    /**
     * 修改密码
     *
     * 当账号尚未设置过密码（数据库 password 字段为空）时，跳过原密码校验，
     * 视为首次设置密码；否则维持原密码校验逻辑。
     */
    public function updatePwd(): bool
    {
        try {
            $or_password = request()->or_password;
            $password = request()->password;
            $member_id = \YunShop::app()->getMemberId();
            $re_password = request()->re_password;

            $member = \app\backend\modules\member\models\Member::uniacid()->where('uid', $member_id)->first();

            // 已设置过密码：必须校验原密码
            if (!empty($member->password)) {
                if (empty($or_password)) {
                    throw new ShopException(self::MSG_ORIGINAL_PWD_ERROR);
                }
                $or_password_hash = md5($or_password . $member->salt);
                if ($member->password != $or_password_hash) {
                    throw new ShopException(self::MSG_ORIGINAL_PWD_ERROR);
                }
            }

            if ($password != $re_password) {
                throw new ShopException(self::MSG_PWD_MISMATCH);
            }
            if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
                throw new ShopException(self::MSG_PWD_TOO_SHORT);
            }

            $data['salt'] = Str::random(8);
            $data['password'] = md5($password . $data['salt']);
            $member->fill($data);
            $member->save();
            return true;
        } catch (ShopException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ShopException(self::MSG_UPDATE_PWD_FAILED);
        }
    }

    /**
     * 实名认证
     */
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
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                return true;
            } else {
                throw new AppException(self::MSG_REALNAME_APPLY_FAILED);
            }
        }
    }

    /**
     * 获取实名状态
     */
    public function getRealNameStatus(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $model = RealNameVerification::where('member_id', $member_id)->first();
        $verify_status = $model ? $model->status : self::VERIFY_STATUS_NOT_VERIFIED;

        $mobile = Member::where('uid', $member_id)->value('mobile');
        return [
            'verify_status' => $verify_status,
            'mobile' => $mobile,
        ];
    }

    /**
     * 企业认证
     */
    public function verifyCompany(array $data): bool
    {
        $member_id = \YunShop::app()->getMemberId();

        $model = CompanyAuths::where('member_id', $member_id)->first();
        if (!$model) {
            $model = new CompanyAuths;
        }
        $data['member_id'] = \YunShop::app()->getMemberId();
        unset($data['code'], $data['mobile']);

        $model->setRawAttributes($data);
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {
            throw new AppException($validator->messages());
        } else {
            if ($model->save()) {
                return true;
            } else {
                throw new AppException(self::MSG_COMPANY_APPLY_FAILED);
            }
        }
    }

    /**
     * 获取企业状态
     */
    public function getCompanyStatus(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $model = CompanyAuths::where('member_id', $member_id)->first();
        $verify_status = $model ? $model->status : self::VERIFY_STATUS_NOT_VERIFIED;

        $mobile = Member::where('uid', $member_id)->value('mobile');
        $data = [
            'verify_status' => $verify_status,
            'mobile' => $mobile,
        ];
        if ($model) {
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
            if ($model->status == self::COMPANY_STATUS_APPROVED) {
                $data['Invoice_type'] = [
                    ['id' => self::INVOICE_TYPE_NORMAL, 'name' => self::INVOICE_TYPE_NAMES[self::INVOICE_TYPE_NORMAL]],
                    ['id' => self::INVOICE_TYPE_SPECIAL, 'name' => self::INVOICE_TYPE_NAMES[self::INVOICE_TYPE_SPECIAL]],
                ];
            } else {
                $data['Invoice_type'] = [
                    ['id' => self::INVOICE_TYPE_NORMAL, 'name' => self::INVOICE_TYPE_NAMES[self::INVOICE_TYPE_NORMAL]],
                ];
            }
        } else {
            $data['Invoice_type'] = [
                ['id' => self::INVOICE_TYPE_NORMAL, 'name' => self::INVOICE_TYPE_NAMES[self::INVOICE_TYPE_NORMAL]],
            ];
        }

        return $data;
    }

    /**
     * 关注供应商列表
     */
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
        }])->paginate(\app\frontend\modules\project\infrastructure\BaseRepository::PAGE_SIZE);
        $data->transform(function ($item) {
            $item->follow_days = $item->created_at->diffInDays(now());
            $item->supplier->logo = yz_tomedia($item->supplier->logo);
            $item->goods->transform(function ($goods) {
                $goods->thumb = yz_tomedia($goods->thumb);
                return $goods;
            });
            return $item;
        });

        return $data->toArray();
    }

    /**
     * 关注案例列表
     */
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
        }])->paginate(\app\frontend\modules\project\infrastructure\BaseRepository::PAGE_SIZE);

        $data->transform(function ($item) {
            $item->industryCase->thumb = yz_tomedia($item->industryCase->thumb);
            $item->industryCase->brandCase->logo = yz_tomedia($item->industryCase->brandCase->logo);
            return $item;
        });

        return $data->toArray();
    }

    /**
     * 获取关注案例标签
     */
    public function getFollowCaseLable(): array
    {
        $memberId = \YunShop::app()->getMemberId();
        $caseIds = CaseFavorite::where('member_id', $memberId)->pluck('case_id')->toArray();
        $ids = IndustryCase::whereIn('id', $caseIds)->pluck('case_id')->toArray();
        return CaseLable::select("id", "name")->whereIn('id', $ids)->get()->toArray();
    }

    /**
     * 获取通知列表
     */
    public function getNotifice(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = Notification::where('member_id', $member_id);
        $search['notice_type'] = $search['notice_type'] ?: self::DEFAULT_NOTICE_TYPE;
        if ($search['notice_type']) {
            $query->where('notice_type', $search['notice_type']);
        }

        $list = $query->orderBy('created_at', 'desc')->paginate(self::NOTIFICATION_PAGE_SIZE);

        return ['list' => $list];
    }

    /**
     * 获取未读消息数量
     */
    public function getUnRead(): array
    {
        $member_id = \YunShop::app()->getMemberId();

        $unreadCounts = Notification::where('member_id', $member_id)
            ->where('is_read', 0)
            ->groupBy('notice_type')
            ->selectRaw('notice_type, count(*) as count')
            ->pluck('count', 'notice_type')
            ->toArray();

        $total = array_sum($unreadCounts);

        return [
            'total' => $total,
            'system' => $unreadCounts['system'] ?? 0,
            'order' => $unreadCounts['order'] ?? 0,
            'audit' => $unreadCounts['audit'] ?? 0,
            'transaction' => $unreadCounts['transaction'] ?? 0,
        ];
    }

    /**
     * 设置消息已读
     */
    public function setRead(array $params): array
    {
        $member_id = \YunShop::app()->getMemberId();

        if (empty($params['type'])) {
            return ['status' => 0, 'message' => self::MSG_MISSING_TYPE];
        }

        $query = Notification::where('member_id', $member_id)->where('is_read', 0);

        switch ($params['type']) {
            case self::READ_ACTION_SINGLE:
                if (empty($params['id'])) {
                    throw new AppException(self::MSG_MISSING_MSG_ID);
                }
                $query->where('id', $params['id']);
                break;

            case self::READ_ACTION_BATCH:
                if (empty($params['ids'])) {
                    throw new AppException(self::MSG_MISSING_MSG_IDS);
                }
                $query->whereIn('id', (array) $params['ids']);
                break;

            case self::READ_ACTION_TYPE:
                if (empty($params['notice_type'])) {
                    throw new AppException(self::MSG_MISSING_NOTICE_TYPE);
                }
                $query->where('notice_type', $params['notice_type']);
                break;

            case self::READ_ACTION_ALL:
                break;

            default:
                throw new AppException(self::MSG_UNSUPPORTED_ACTION);
        }

        $updated = $query->update(['is_read' => 1, 'read_at' => time()]);

        return [
            'data' => [
                'updated_count' => $updated,
            ],
        ];
    }

    /**
     * 脱敏处理
     */
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
}
