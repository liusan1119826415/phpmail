<?php

namespace app\frontend\modules\project\services\goods;

use app\frontend\modules\goods\models\Comment;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\common\traits\GoodsOptionTrait;
class GoodsCommentService
{   
    use GoodsOptionTrait;


    public function getComment()
    {
        $baseRepo = $this->getBaseRepo();

        $goods_id = request()->goods_id;
        $filter = request()->filter;
        $fieldMapping = [
            'time' => 'created_at',
            'score' => 'level',
        ];
        $sort = request()->sort;
        $name = $fieldMapping[$sort] ?? 'created_at';

        // 基础查询条件
        $baseQuery = Comment::uniacid()
            ->where('type', 1) // 主评论
            ->where('goods_id', $goods_id)
            ->where('is_show', 1);

        // 统计数据
        $stats = [
            'all' => (clone $baseQuery)->count(),
            'good' => (clone $baseQuery)->whereBetween('level', [4, 5])->count(),
            'medium' => (clone $baseQuery)->where('level', 3)->count(),
            'bad' => (clone $baseQuery)->whereBetween('level', [1, 2])->count(),
        ];

        // 主查询
        $query = (clone $baseQuery)
            ->with([
                'orderGoods' => function ($query) {
                    $query->select("id", "components", "goods_option_title", "type");
                },
                // 一级回复（直接回复主评论）
                'hasManyReply' => function ($query) {
                    $query->where('type', 2)
                        ->where('is_show', 1)
                        ->with([
                            // 二级回复（回复的回复）
                            'hasManyReply' => function ($query) {
                                $query->where('type', 2)
                                    ->where('is_show', 1);
                            }
                        ]);
                }
            ]);

        // 筛选条件
        switch ($filter) {
            case 'good':
                $query->whereBetween('level', [4, 5]);
                break;
            case 'medium':
                $query->where('level', 3);
                break;
            case 'bad':
                $query->whereBetween('level', [1, 2]);
                break;
        }

        $comment_data = $query->orderBy($name, 'desc')->paginate(5);

        // 转换数据
        $comment_data->transform(function ($item) use ($baseRepo) {
            $item->nick_name = substrCut2($item->nick_name);
            $item->images = array_map(fn($url) => yz_tomedia($url), @unserialize($item->images) ?: []);

            // 处理 orderGoods 数据
            if ($item->orderGoods) {
                $item->material_color = $baseRepo->getMaterialColor($item->orderGoods->toArray());
            }

            // 确保一级回复是集合
            $item->hasManyReply = $item->hasManyReply ?? collect();

            // 临时存储所有回复（一级+二级）
            $allReplies = collect();

            $item->hasManyReply->each(function ($reply) use (&$allReplies) {
                // 处理一级回复
                $reply->nick_name = substrCut2($reply->nick_name);
                $reply->images = array_map(fn($url) => yz_tomedia($url), @unserialize($reply->images) ?: []);

                // 标记为一级回复（可选）
                $reply->is_first_level = true;
                $allReplies->push($reply);

                // 处理二级回复并追加到集合
                $reply->hasManyReply = $reply->hasManyReply ?? collect();
                $reply->hasManyReply->each(function ($nested) use (&$allReplies) {
                    $nested->nick_name = substrCut2($nested->nick_name);
                    $nested->images = array_map(fn($url) => yz_tomedia($url), @unserialize($nested->images) ?: []);

                    // 标记为二级回复（可选）
                    $nested->is_first_level = false;
                    $allReplies->push($nested);
                });
            });

            // 替换原始回复为合并后的集合
            $item->hasManyReply = $allReplies;

            return $item;
        });

        return [
            'comments' => $comment_data,
            'stats' => $stats
        ];
    }
}
