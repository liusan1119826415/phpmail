<?php

namespace app\frontend\modules\project\services\diymodel;

use app\common\exceptions\AppException;
use app\common\models\project\CloudDesign;
use app\common\models\project\Project;
use app\frontend\modules\project\infrastructure\BaseRepository;
use Illuminate\Support\Facades\DB;

class DesignCrudService
{
    const PAGE_SIZE = 6;

    protected DesignAssemblyService $assemblyService;

    public function __construct(DesignAssemblyService $assemblyService)
    {
        $this->assemblyService = $assemblyService;
    }

    protected function getBaseRepo(): BaseRepository
    {
        return app(BaseRepository::class);
    }

    /**
     * 修改方案名称
     */
    public function renameDesign(int $designId, string $name): bool
    {
        $design = CloudDesign::findOrFail($designId);
        $design->name = $name;
        return $design->save();
    }

    /**
     * 删除方案
     */
    public function deleteDesign(int $designId): bool
    {
        $design = CloudDesign::findOrFail($designId);
        return $design->delete();
    }

    /**
     * 回收站方案列表
     */
    public function designRecycleList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = CloudDesign::uniacid()->select("id", "name", "created_at", "updated_at", "deleted_at")->where('member_id', $member_id)->withTrashed()->whereNotNull('deleted_at');
        if ($search['name']) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;

        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('created_at', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('created_at', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('created_at', '<=', $search['end_date']);
        }

        $query->orderBy('deleted_at', 'desc');

        $data = $query->paginate(self::PAGE_SIZE);

        return $data ? $data->toArray() : [];
    }

    /**
     * 恢复方案
     */
    public function restoreDesign(int $designId): bool
    {
        $design = CloudDesign::withTrashed()->findOrFail($designId);
        return $design->restore();
    }

    /**
     * 真实删除
     */
    public function deleteDesignReal(int $designId): bool
    {
        $design = CloudDesign::withTrashed()->findOrFail($designId);
        return $design->forceDelete();
    }

    /**
     * 复制设计方案
     */
    public function duplicateDesign($designId): bool
    {
        $memberId = \YunShop::app()->getMemberId();
        return DB::transaction(function () use ($designId, $memberId) {
            $original = CloudDesign::findOrFail($designId);

            $newName = $this->generateDuplicateName($original->name, $original->member_id);

            $duplicate = new CloudDesign();
            $duplicate->uniacid = $original->uniacid;
            $duplicate->member_id = $memberId ?: $original->member_id;
            $duplicate->province_id = $original->province_id;
            $duplicate->city_id = $original->city_id;
            $duplicate->district_id = $original->district_id;
            $duplicate->name = $newName;
            $duplicate->price = $original->price;
            $duplicate->thumbnail = $original->thumbnail;
            $duplicate->design_data = $original->design_data;
            $duplicate->created_at = time();
            $duplicate->updated_at = time();

            $duplicate->save();

            return true;
        });
    }

    /**
     * 上传dwg或dxf 文件
     */
    public function uploadDwg(): array
    {
        $file = request()->file('file');

        if (!$file) {
            throw new AppException('请上传文件');
        }

        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['dwg', 'dxf'])) {
            throw new AppException('只支持 DWG 或 DXF 文件格式');
        }

        if ($extension === 'dwg') {
            $filePath = $file->storeAs('uploads', $originalName);
            $response = $this->getBaseRepo()->curl_analysis_cad("search_block", $filePath);
            if ($response['data']['status_code'] == 200) {
                $dxf_file = $response['data']['dxf_file'];
                $dxf_url = "https://" . Request()->getHost() . "/dxf/{$dxf_file}";
                return ['url' => $dxf_url, 'originalName' => $originalName];
            } else {
                throw new AppException($response['data']['message']);
            }
        } else {
            $response = uploadOss($file, $originalName);
            return ['url' => $response['absolute_path'], 'originalName' => $originalName];
        }
    }

    /**
     * 保存方案
     */
    public function saveDesign(array $data): array
    {
        $data['design_data'] = $this->toJson($data['design_data']);

        return $this->getBaseRepo()->transaction(function () use ($data) {
            if (empty($data['name'])) {
                throw new \Exception('方案名称不能为空');
            }

            if ($data['imgBase64'] ?? null) {
                $thumb = BaseRepository::uploadOssThumb($data['imgBase64']);
            }

            $projectId = null;
            if ($data['id']) {
                $design = CloudDesign::where('id', $data['id'])
                    ->first();
                $project_id = $design->project_id;
            }
            if (($data['save_project'] ?? 0) == 1) {
                $projectData = [
                    'uniacid' => \YunShop::app()->uniacid,
                    'member_id' => \YunShop::app()->getMemberId(),
                    'name' => $data['name'],
                ];

                if (!empty($project_id)) {
                    $project = Project::where('id', $project_id)
                        ->where('member_id', \YunShop::app()->getMemberId())
                        ->firstOrFail();
                    $project->fill($projectData);
                    $project->save();
                    $projectId = $project->id;
                } else {
                    $project = new Project($projectData);
                    $project->save();
                    $projectId = $project->id;
                }
            }

            $saveData = [
                'uniacid' => \YunShop::app()->uniacid,
                'member_id' => \YunShop::app()->getMemberId(),
                'name' => $data['name'],
                'thumbnail' => $thumb ?? '',
                'design_data' => $data['design_data'] ?? null,
            ];
            if ($projectId) {
                $saveData['project_id'] = $projectId;
            }

            $old_design_data = [];
            if (!empty($data['id'])) {
                $old_design_data = $design->design_data;
                $design->fill($saveData);
                $result = $design->save();
            } else {
                $saveData['created_at'] = time();
                $design = new CloudDesign($saveData);
                $result = $design->save();
            }

            if (!empty($data['design_data']) && $data['save_project'] == 1) {
                if ($project->order_status == 1) {
                    throw new AppException('项目订单已经提交，不能覆盖');
                }

                $this->assemblyService->handleDesignData($data['design_data'], $projectId, $old_design_data);
            }

            return ['id' => $design->id];
        });
    }

    /**
     * 方案管理列表
     */
    public function designList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $page_size = request()->input('page_size');
        $sort = $search['sort'] ?? 'created_desc';

        $query = CloudDesign::uniacid()->select("id", "name", "thumbnail", "created_at", "updated_at")->where('member_id', $member_id);

        if ($search['name']) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : null;
        $search['end_date'] = $search['end_date'] ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;

        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('created_at', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('created_at', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('created_at', '<=', $search['end_date']);
        }

        switch ($sort) {
            case 'created_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'updated_desc':
                $query->orderBy('updated_at', 'desc');
                break;
            case 'updated_asc':
                $query->orderBy('updated_at', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        $data = $query->paginate($page_size ?: self::PAGE_SIZE);
        $data->transform(function ($item) {
            $item->thumbnail = $item->thumbnail ? yz_tomedia($item->thumbnail) : "";
            $item->time_text = $this->formatTimeText($item->created_at);

            return $item;
        });

        return $data->toArray();
    }

    /**
     * 判断格式是否为数组，为json就转数组
     */
    public function toJson($data): array
    {
        if (is_array($data)) {
            return $data;
        }
        $json = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
        return [];
    }

    /**
     * 格式化时间显示
     */
    private function formatTimeText($timestamp): string
    {
        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return '刚刚';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . '分钟前';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . '小时前';
        }

        if ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . '天前';
        }

        if ($diff < 31536000) {
            return date('m-d', $timestamp);
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * 生成唯一的副本名称
     */
    private function generateDuplicateName($originalName, $memberId)
    {
        $baseName = $this->getBaseName($originalName);
        $newName = $baseName . '_副本';

        $counter = 1;
        $finalName = $newName;

        while ($this->nameExists($finalName, $memberId)) {
            $finalName = $newName . $counter;
            $counter++;
        }

        return $finalName;
    }

    /**
     * 获取基础名称（去除已有的副本后缀）
     */
    private function getBaseName($name)
    {
        if (preg_match('/^(.*)_副本\d*$/', $name, $matches)) {
            return $matches[1];
        }

        if (str_ends_with($name, '_副本')) {
            return substr($name, 0, -3);
        }

        return $name;
    }

    /**
     * 检查名称是否已存在
     */
    private function nameExists($name, $memberId)
    {
        return CloudDesign::where('member_id', $memberId)
            ->where('name', $name)
            ->exists();
    }
}
