<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\MemberRepositoryInterface;
class MemberService
{
    private MemberRepositoryInterface $memberRepository;

    public function __construct(MemberRepositoryInterface $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }


    public function storeUserProfile(array $data)
    {
        return $this->memberRepository->storeUserProfile($data);
    }


    public function getIndustry(int $id)
    {
        return $this->memberRepository->getIndustry($id);
    }

    public function getMemberInfo()
    {
        return $this->memberRepository->getMemberInfo();
    }

    public function updatePwd()
    {
        return $this->memberRepository->updatePwd();
    }

    public function verifyRealName( array $data)
    {
        return $this->memberRepository->verifyRealName($data);
    }

    public function getRealNameStatus()
    {
        return $this->memberRepository->getRealNameStatus();
    }

    public function verifyCompany( array $data)
    {
        return $this->memberRepository->verifyCompany($data);
    }

    public function getCompanyStatus()
    {
        return $this->memberRepository->getCompanyStatus();
    }

    public function applySupplier(array $data)
    {
        return $this->memberRepository->applySupplier($data);
    }

    public function getApplyStatus()
    {
        return $this->memberRepository->getApplyStatus();
    }

    public function improveShop(array $data)
    {
        return $this->memberRepository->improveShop($data);
    }

    public function getFollowSupplier($name)
    {
        return $this->memberRepository->getFollowSupplier($name);
    }

    public function getFollowCase(array $search)
    {
        return $this->memberRepository->getFollowCase($search);
    }

    public function getFollowCaseLable()
    {
        return $this->memberRepository->getFollowCaseLable();
    }

    public function applyStepOne(array $data)
    {
        return $this->memberRepository->applyStepOne($data);
    }

    public function applyStepTwo(array $data)
    {
        return $this->memberRepository->applyStepTwo($data);
    }

    public function applyStepThree(array $data)
    {
        return $this->memberRepository->applyStepThree($data);
    }

    public function applyAccountVerify(array $data)
    {
        return $this->memberRepository->applyAccountVerify($data);
    }

    public function amountVerify(array $data)
    {
        return $this->memberRepository->amountVerify($data);
    }

    public function generateQrCode()
    {
        return $this->memberRepository->generateQrCode();
    }

    public function handleCallback()
    {
        return $this->memberRepository->handleCallback();
    }

    public function getNotifice($search)
    {
        return $this->memberRepository->getNotifice($search);
    }

    public function setRead($search)
    {
        return $this->memberRepository->setRead($search);
    }

    public function getUnRead()
    {
        return $this->memberRepository->getUnRead();
    }









}