<?php


namespace app\backend\modules\cloud\services;

use app\common\exceptions\ShopException;
use app\common\models\project\IssueOptions;
use app\common\models\Member;

class IssueTypeService
{


    public function getList($keyword)
    {
        $query = IssueOptions::select("id", "title", "parent_id", "status")
            ->with(['children' => function ($query) {
                $query->select("id", "title", "parent_id", "status");
            }])
            ->where('parent_id', 0);

        if (!empty($keyword)) {
            $title = $keyword;

            $query->where(function ($q) use ($title) {
                // 1. 查询父级本身的 title
                $q->where('title', 'like', "%{$title}%")

                    // 2. 或者查询子级的 title
                    ->orWhereHas('children', function ($childQuery) use ($title) {
                        $childQuery->where('title', 'like', "%{$title}%");
                    });
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(20);

        return $data;
    }



    /**
     * 保存问题类型
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function save($data, $id = 0)
    {
        if ($id) {
            $issueOption = IssueOptions::find($id);
            if (!$issueOption) {
                return false;
            }
        } else {
            $issueOption = new IssueOptions();
        }

        $issueOption->title = $data['title'];
        $issueOption->status = $data['status'];
        if ($id == 0) {
            $issueOption->parent_id = isset($data['parent_id']) ? $data['parent_id'] : 0;
        }


        return $issueOption->save();
    }

    /**
     * 获取问题类型详情
     */
    public function getOne($id)
    {
        $issueOption = IssueOptions::select("id", "title", "parent_id", "status")->find($id);
        if (!$issueOption) {
            return false;
        }
        return $issueOption;
    }

    /**
     * 是否启动
     */
    public function enable($id)
    {
        $issueOption = IssueOptions::find($id);
        if (!$issueOption) {
            return false;
        }
        $issueOption->status = $issueOption->status ? 0 : 1;
        return $issueOption->save();
    }

    /**
     * 删除问题类型
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $issueOption = IssueOptions::find($id);
        if ($issueOption->children->isNotEmpty()) {
            throw new ShopException('请先删除子类');
        }
        if (!$issueOption) {
            return false;
        }
        return $issueOption->delete();
    }
}
