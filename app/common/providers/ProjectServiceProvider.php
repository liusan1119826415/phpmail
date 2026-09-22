<?php


namespace app\common\providers;

use app\common\service\NotificationService;
use app\frontend\modules\project\infrastructure\BidProjectRepository;
use app\frontend\modules\project\infrastructure\CaseRepository;
use app\frontend\modules\project\infrastructure\DoorOrderRepository;
use app\frontend\modules\project\infrastructure\FactoryInspectionRepository;
use app\frontend\modules\project\infrastructure\MemberRepository;
use app\frontend\modules\project\infrastructure\OrderInvoiceRepository;
use app\frontend\modules\project\infrastructure\ProjectRepository;
use app\frontend\modules\project\infrastructure\ReportProjectRepository;
use app\frontend\modules\project\repositories\BidProjectRepositoryInterface;
use app\frontend\modules\project\repositories\CaseRepositoryInterface;
use app\frontend\modules\project\repositories\DoorOrderRepositoryInterface;
use app\frontend\modules\project\repositories\FactoryInspectionRepositoryInterface;
use app\frontend\modules\project\repositories\MemberRepositoryInterface;
use app\frontend\modules\project\repositories\OrderInvoiceRepositoryInterface;
use app\frontend\modules\project\repositories\ProjectRepositoryInterface;
use app\frontend\modules\project\repositories\ReportProjectRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use app\frontend\modules\project\repositories\SpaceRepositoryInterface;
use app\frontend\modules\project\infrastructure\SpaceRepository;
use app\frontend\modules\project\repositories\GoodsRepositoryInterface;
use app\frontend\modules\project\infrastructure\GoodsRepository;
use app\frontend\modules\project\repositories\BrandRepositoryInterface;
use app\frontend\modules\project\infrastructure\BrandRepository;
use app\frontend\modules\project\repositories\QuoteRepositoryInterface;
use app\frontend\modules\project\infrastructure\QuoteRepository;
use app\frontend\modules\project\repositories\PptRepositoryInterface;
use app\frontend\modules\project\infrastructure\PptRepository;
use app\frontend\modules\project\repositories\BidOrderRepositoryInterface;
use app\frontend\modules\project\infrastructure\BidOrderRepository;

use app\frontend\modules\project\repositories\OrderRepositoryInterface;
use app\frontend\modules\project\infrastructure\OrderRepository;
class ProjectServiceProvider extends ServiceProvider
{


    public function register()
    {
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);

        $this->app->bind(SpaceRepositoryInterface::class, SpaceRepository::class);

        $this->app->bind(GoodsRepositoryInterface::class, GoodsRepository::class);

        $this->app->bind(BrandRepositoryInterface::class, BrandRepository::class);

        $this->app->bind(QuoteRepositoryInterface::class, QuoteRepository::class);

        $this->app->bind(PptRepositoryInterface::class, PptRepository::class);

        $this->app->bind(CaseRepositoryInterface::class, CaseRepository::class);

        $this->app->bind(ReportProjectRepositoryInterface::class, ReportProjectRepository::class);

        $this->app->bind(BidProjectRepositoryInterface::class,BidProjectRepository::class);

        $this->app->bind(FactoryInspectionRepositoryInterface::class,FactoryInspectionRepository::class);

        $this->app->bind(BidOrderRepositoryInterface::class,BidOrderRepository::class);

        $this->app->bind(OrderRepositoryInterface::class,OrderRepository::class);

        $this->app->bind(MemberRepositoryInterface::class,MemberRepository::class);

        $this->app->bind(DoorOrderRepositoryInterface::class,DoorOrderRepository::class);


        $this->app->bind(OrderInvoiceRepositoryInterface::class,OrderInvoiceRepository::class);

        $this->app->singleton('notification', function ($app) {
            return new \app\common\modules\pcnotice\NotificationService();
        });

    }

}