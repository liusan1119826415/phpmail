<?php


namespace app\backend\modules\goods\services;


use app\common\models\Goods;
use app\frontend\models\GoodsOption;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

class GoodsMeiliSearchService
{

    public function __construct()
    {

        $this->client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

        $this->index = $this->client->index('goods_option');

        $this->configureIndex();


    }

    protected function configureIndex()
    {
        $this->index->updateSettings([
            'searchableAttributes' => [
                'goods_title',
                'option_title',
                'goods_keywords',
                'category_name',
                'supplier_name',
                'material_name',
                'style_name'
            ],
            'filterableAttributes' => [
                'category_id', 'supplier_id',
                'material_id', 'style_id',
                'supplier_city_id', 'bid_enable', 'is_stock', 'lead_time', 'price','status','enable','goods_id','length'
            ],
            'sortableAttributes' => [
                'price', 'show_sales', 'comment_num', 'created_at','display_order'
            ],
            'rankingRules' => [
                /*'typo',
                'words',
                'proximity',
                'attribute',
                'exactness',
                'sort',*/
                'exactness',
                'words',
                'proximity',
                'attribute',
                'typo',
                'sort'
            ],
            'synonyms' => [

                // === 办公椅类 ===
                '办公椅' => ['转椅','职员椅','电脑椅','写字椅','网布椅','办公座椅','办公转椅','人体工学椅','椅子'],
                '转椅' => ['办公椅','职员椅','电脑椅','写字椅','网布椅'],
                '职员椅' => ['办公椅','转椅','电脑椅','写字椅','办公座椅'],
                '电脑椅' => ['办公椅','转椅','职员椅','写字椅','人体工学椅'],
                '人体工学椅' => ['办公椅','电脑椅','职员椅','转椅'],
                '办公椅' => ['办公座椅','职员椅','电脑椅','写字椅','办公转椅','网布椅','工学椅'],
                '会议椅' => ['会议座椅','洽谈椅','会客椅','培训椅','会议靠椅'],
                '老板椅' => ['大班椅','行政椅','经理椅','主管椅','皮椅','真皮椅'],
                '休闲椅' => ['躺椅','摇椅','沙滩椅','午休椅','折叠椅'],
                '餐椅'   => ['饭椅','餐桌椅','就餐椅','食堂椅','餐厅椅'],
                '办公桌' => ['写字台','电脑桌','职员桌','办公台','办公台面'],
                '会议桌' => ['会议台','长桌','洽谈桌','培训桌','会议长台','会客桌'],
                '老板桌' => ['大班桌','行政桌','经理桌','主管桌','老板台'],
                '前台桌' => ['接待台','迎宾台','收银台','前台柜','接待桌'],
                '餐桌'   => ['饭桌','餐台','餐桌台面','餐厅桌'],

                '文件柜' => ['档案柜','资料柜','铁皮柜','储物柜','资料架','文件箱'],
                '更衣柜' => ['衣柜','储物柜','收纳柜','铁皮柜','更换柜'],
                '书柜'   => ['书架','资料柜','展示柜','文件柜'],
                '展示柜' => ['陈列柜','玻璃柜','展柜','展示架'],
                '收纳柜' => ['储物柜','整理柜','收纳箱','置物柜'],

                // === 办公桌类 ===
                '办公桌' => ['写字台','电脑桌','职员桌','办公台','办公台面','办公台桌'],
                '写字台' => ['办公桌','电脑桌','职员桌','办公台'],
                '电脑桌' => ['办公桌','写字台','职员桌','办公台'],
                '职员桌' => ['办公桌','写字台','电脑桌'],

                // === 老板桌/行政桌 ===
                '老板桌' => ['大班桌','行政桌','经理桌','主管桌'],
                '大班桌' => ['老板桌','行政桌','经理桌'],
                '行政桌' => ['老板桌','大班桌','经理桌'],
                '经理桌' => ['老板桌','大班桌','行政桌'],
                '班台' => ['大班台','小班台'],

                // === 会议类 ===
                '会议桌' => ['会议台','长桌','洽谈桌','会议台面'],
                '会议椅' => ['会议座椅','洽谈椅','会客椅','会议用椅'],

                // === 沙发类 ===
                '办公沙发' => ['会客沙发','休闲沙发','接待沙发','真皮沙发','布艺沙发','沙发'],
                '会客沙发' => ['办公沙发','接待沙发','休闲沙发'],
                '布艺沙发' => ['沙发','办公沙发','会客沙发','布沙发'],
                '真皮沙发' => ['皮沙发','办公沙发','会客沙发'],

                // === 柜子类 ===
                '文件柜' => ['档案柜','资料柜','铁皮柜','储物柜','资料架'],
                '档案柜' => ['文件柜','资料柜','铁皮柜'],
                '资料柜' => ['文件柜','档案柜','铁皮柜'],
                '储物柜' => ['文件柜','档案柜','资料柜','更衣柜'],
                '更衣柜' => ['储物柜','衣柜','铁皮柜','更换柜'],

                // === 桌组组合 ===
                '工作站' => ['工位','屏风位','办公位','职员位','屏风工位'],
                '工位' => ['工作站','屏风位','办公位','职员位'],
                '屏风位' => ['工作站','工位','办公位'],

                // === 前台 ===
                '前台桌' => ['接待台','迎宾台','收银台','前台柜'],
                '接待台' => ['前台桌','迎宾台','接待桌'],
                '椅子凳子' => ['椅子', '凳子'],
                '椅凳' => ['椅子', '凳子','办公椅'],
                // === 茶几/会议配套 ===
                '茶几' => ['会客几','休闲几','边几','小桌几'],
                '书柜' => ['书架','文件柜','资料柜','展示柜'],
                '办公柜' => ['文件柜','储物柜','档案柜','展示柜'],

                // === 家具扩展（家用） ===
                '床' => ['单人床','双人床','高低床','上下床','大床'],
                '单人床' => ['床','小床','学生床'],
                '双人床' => ['床','大床','婚床'],
                '沙发床' => ['沙发','床','折叠床'],

                '餐桌' => ['饭桌','吃饭桌','餐台','餐桌台面'],
                '饭桌' => ['餐桌','餐台','吃饭桌'],
                '餐椅' => ['饭椅','餐桌椅','椅子'],

                '茶几' => ['小桌子','边桌','会客几'],
                '衣柜' => ['储物柜','更衣柜','收纳柜'],

                // === 其他常用办公/家居 ===
                '书桌' => ['写字台','办公桌','电脑桌'],
                '躺椅' => ['休闲椅','摇椅','沙滩椅'],
                '展示柜' => ['陈列柜','玻璃柜','展柜'],
                '收纳柜' => ['储物柜','整理柜','收纳箱'],
            ],
            'stopWords' => ['的', '了', '和', '与', '及', '是', '在', '有', '我', '你','子',
                '他', '她', '它']

        ]);
    }


    /**
     * 全量同步
     */
    public function reindexAll()
    {
        $optionIds = GoodsOption::where("is_default", 1)->whereHas('goods',function ($query){
            $query->where('type2',1)->where('status',1)->whereNull('deleted_at'); // 必须手动添加
        })->pluck('id')->toArray();
        $documents = [];

        foreach ($optionIds as $id) {
            $doc = $this->buildDocument($id);
            if ($doc) {
                $documents[] = $doc;
            }
        }

        if (!empty($documents)) {
            // 一次性添加/更新所有文档
            $task = $this->index->addDocuments($documents, 'id');
            $completedTask = $this->client->waitForTask($task['taskUid']);

            if ($completedTask['status'] === 'succeeded') {
                echo "文档批量添加/更新成功！共 " . count($documents) . " 条\n";
                return true;
            } else {
                echo "文档同步失败: " . $completedTask['error']['message'] ?? '未知错误';
                return false;
            }
        }

        echo "没有可同步的文档\n";
        return false;
    }


    /**
     * @return \Meilisearch\Endpoints\Indexes
     */
    public function deleteIndex($indexName)
    {
        $this->client->deleteIndex($indexName);
    }


    /**
     * 构建单条商品规格索引文档
     */
    protected function buildDocument($option_id)
    {
        $item = DB::table('yz_goods_option as o')
            ->leftJoin('yz_goods as g', 'g.id', '=', 'o.goods_id')
            ->leftJoin('yz_supplier as s', 's.id', '=', 'o.supp_id')
            ->where('o.id', $option_id)
            ->select(
                'o.id as option_id',
                'o.goods_id',
                'o.title as option_title',
                'o.product_price as price',
                'o.show_sales',
                'o.comment_num',

                'o.stock',
                'o.thumb',
                'o.length',
                'g.created_at',
                'g.title as goods_title',
                'g.is_discount',
                'g.is_hot',
                'g.keywords as goods_keywords',
                'g.supp_id as supplier_id',
                's.store_name as supplier_name',
                's.city_id as supplier_city_id',
                's.bid_enable',
                's.enable',
                'g.is_stock',
                'g.lead_time',
                'g.status'
            )->first();


        if (!$item) return null;

        // 多分类
        $categories = DB::table('yz_goods_category as r')
            ->leftJoin('yz_category as c', 'r.category_id', '=', 'c.id')
            ->leftJoin('yz_category as p', 'c.parent_id', '=', 'p.id') // 父级分类
            ->where('r.goods_id', $item['goods_id'])
            ->select(
                'r.category_id as id',
                'c.name',
                'c.parent_id',
                'p.id as parent_id',
                'p.name as parent_name'
            )
            ->get();

        $category_ids = [];
        $category_names = [];

        foreach ($categories as $cat) {
            // 二级分类
            $category_ids[] = $cat['id'];
            $category_names[] = $cat['name'];

            // 一级分类（如果存在且不重复）
            if (!empty($cat['parent_id'])) {
                $category_ids[] = $cat['parent_id'];
                $category_names[] = $cat['parent_name'];
            }
        }

        // 去重
        $category_ids = array_unique($category_ids);
        $category_names = array_unique($category_names);

        // 拼接成字符串

        $category_names = implode('|', $category_names);


        // 材质/风格
        $attributes = DB::table('yz_product_style_relations as r')
            ->leftJoin('yz_product_styles as s', 'r.style_id', '=', 's.id')
            ->where('r.goods_id', $item['goods_id'])
            ->select('r.type', 'r.style_id', 's.name')
            ->get();


        $materialArr = ['material_id' => [], 'material' => []];
        $styleArr = ['style_id' => [], 'style' => []];

        foreach ($attributes as $attr) {
            if ($attr['type'] == 2) {
                $materialArr['material_id'][] = $attr['style_id'];
                $materialArr['material'][] = $attr['name'];

            } elseif ($attr['type'] == 1) {
                $styleArr['style_id'][] = $attr['style_id'];
                $styleArr['style'][] = $attr['name'];
            }
        }

        $material_names = implode("|", $materialArr['material']);

        $style_names = implode("|", $styleArr['style']);

        return [
            'id' => (int)$item['option_id'],
            'option_id' => (int)$item['option_id'],
            'goods_id' => (int)$item['goods_id'],
            'option_title' => $item['option_title'],
            'price' => (float)$item['price'],
            'show_sales' => (int)$item['show_sales'],
            'comment_num' => (int)$item['comment_num'],
            'is_stock' => (int)$item['is_stock'],
            'length' => (float)$item['length'],
            'stock' => (int)$item['stock'],
            'goods_title' => $item['goods_title'],
            'goods_keywords' => $item['goods_keywords'],
            'category_id' => $category_ids,
            'category_name' => $category_names,
            'supplier_name' => $item['supplier_name'],
            'supplier_id' => $item['supplier_id'],
            'supplier_city_id' => $item['supplier_city_id'],
            'bid_enable' => $item['bid_enable'],
            'lead_time' => $item['lead_time'],
            'material_id' => $materialArr['material_id'],
            'material_name' => $material_names,
            'style_id' => $styleArr['style_id'],
            'style_name' => $style_names,
            'created_at' => (int)$item['created_at'],
            'thumb' => yz_tomedia($item['thumb']),
            'is_discount'=>(int)$item['is_discount'],
            'is_hot'=>(int)$item['is_hot'],
            'status'=>(int)$item['status'],
            'enable'=>(int)$item['enable'],

        ];
    }

    public function reindexByGoodsId($goodsId)
    {



        $task = $this->index->deleteDocuments([
            'filter' => "goods_id = {$goodsId}"
        ]);
        $res = $this->client->waitForTask($task['taskUid']);

        // 1. 删除该 goods_id 的旧文档
        $newOptionIds = GoodsOption::where('goods_id', $goodsId)->where('is_default',1)->whereHas('goods',function ($query){
            $query->where('type2',1);
        })->pluck('id')->toArray();

        // 2. 重新构建文档
        $docs = [];
        foreach ($newOptionIds as $id) {
            $doc = $this->buildDocument($id);
            if ($doc) {
                $docs[] = $doc;
            }
        }

        // 3. 批量插入
        if (!empty($docs)) {
            $task = $this->index->addDocuments($docs, 'id');

            $completedTask = $this->client->waitForTask($task['taskUid']);

            \Log::debug("=====completedTask====",$completedTask);
            if ($completedTask['status'] === 'succeeded') {
                \Log::debug("goods_id={$goodsId} 索引更新成功！");
            } else {
                \Log::error("索引更新失败: " . $completedTask['error']['message']);
            }
        }
    }




    public function deleteByGoodsId($goodsId)
    {


        if (!empty($goodsId)) {
            try {
                $task = $this->index->deleteDocuments([
                    'filter' => "goods_id = {$goodsId}"
                ]);
                $completedTask = $this->client->waitForTask($task['taskUid']);

                if ($completedTask['status'] === 'succeeded') {
                    \Log::debug("goods_id={$goodsId} 索引删除成功！");
                } else {
                    \Log::error("goods_id={$goodsId} 索引删除失败: " . $completedTask['error']['message']);
                }
            } catch (\Exception $e) {
                \Log::error("删除 goods_id={$goodsId} 索引异常: " . $e->getMessage());
            }
        } else {
            \Log::debug("goods_id={$goodsId} 没有可删除的索引文档");
        }
    }





    /**
     * 删除规格/商品
     */
    public function deleteOption(int $optionId)
    {
        $this->index->deleteDocument($optionId);
    }

}