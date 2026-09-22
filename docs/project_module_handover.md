# Project 模块交接文档 — 代码结构与 API 接口

> **位置**: `app/frontend/modules/project/`
> **更新**: 2026-05-27
> **说明**: 本文档覆盖 Project 模块的完整目录结构、Controller/Service/Repository/Infrastructure/Model 各层职责、以及全部 API 接口清单（含请求参数和返回格式）。

---

## 1. 目录结构全景

```
app/frontend/modules/project/
├── controllers/          # 20 个 API 控制器（约 200+ 接口）
│   ├── GoodsController.php              # 商品详情/搜索/CAD解析/推荐
│   ├── GoodsSearchController.php        # MeiliSearch 商品搜索 (V2)
│   ├── GoodsSearchTestController.php    # 搜索测试控制器 (V1)
│   ├── ProjectController.php            # 项目方案管理 (30+ 接口)
│   ├── OrderController.php              # 订单全生命周期 (27 接口)
│   ├── MemberController.php             # 会员/供应商/认证 (26 接口)
│   ├── BrandController.php              # 品牌/供应商浏览
│   ├── CaseController.php               # 行业案例
│   ├── BidProjectController.php         # 投标项目管理
│   ├── BidOrderController.php           # 投标订单
│   ├── DoorOrderController.php          # 上门测量订单
│   ├── DiyModelController.php           # 云设计 (DIY 模型)
│   ├── FactoryInspectionController.php   # 工厂考察申请
│   ├── ReportProjectController.php      # 项目报备
│   ├── OrderInvoiceController.php       # 发票管理
│   ├── QuoteController.php              # 报价模板
│   ├── PptController.php               # PPT 方案书生成
│   ├── SearchHistoryController.php      # 搜索历史
│   ├── SpaceController.php              # 空间管理 (1029KB 超大文件)
│   └── DownloadController.php           # 下载限流检测
│
├── services/             # 45 个 Service 类（业务逻辑层）
│   ├── GoodsBaseService.php             # ★ 核心: 商品数据加载/3D模型/规格树 (70KB)
│   ├── GoodsSearchService.php           # MeiliSearch V1 搜索 (39.7KB)
│   ├── GoodsSearchServiceV2.php         # MeiliSearch V2 搜索 (38.8KB)
│   ├── GoodsService.php                 # 商品通用服务
│   ├── CategoryRecService.php           # 分类推荐算法 (28.2KB)
│   ├── CategoryRecServiceV3.php         # 分类推荐 V3 (21.2KB)
│   ├── ProjectService.php              # 项目方案管理
│   ├── PreOrderService.php             # 预下单/订单创建
│   ├── OrderService.php                # 订单服务
│   ├── MemberService.php               # 会员/供应商服务
│   ├── BrandService.php                # 品牌服务
│   ├── CaseService.php                 # 案例服务
│   ├── DiyModelService.php             # 云设计服务
│   ├── DownloadLimitService.php         # ★ 下载限流 (11.1KB)
│   ├── GoodsComparator.php             # 商品对比服务 (20.8KB)
│   ├── GoodsOptionClickService.php     # 商品规格点击追踪 (8KB)
│   ├── RecommendGoodsService.php       # 推荐商品 (9.8KB)
│   ├── SearchTitleService.php          # 搜索标题模糊匹配
│   ├── SearchHistoryService.php        # 搜索历史
│   ├── AssemblyGoodsService.php        # 商品组装 (22.7KB)
│   ├── SpaceService.php               # 空间服务
│   ├── BidProjectService.php           # 投标项目
│   ├── BidOrderService.php             # 投标订单
│   ├── DoorOrderService.php            # 上门订单
│   ├── FactoryInspectionService.php    # 工厂考察
│   ├── ReportProjectService.php        # 项目报备
│   ├── OrderInvoiceService.php         # 发票服务
│   ├── QuoteService.php               # 报价模板
│   ├── PptService.php                  # PPT 方案书
│   ├── related/                        # 关联商品子服务 (4个)
│   └── ...（备份文件约 15 个，以 copy/日期后缀命名）
│
├── repositories/         # 16 个 Repository 接口
│   ├── GoodsRepositoryInterface.php
│   ├── ProjectRepositoryInterface.php
│   ├── OrderRepositoryInterface.php
│   ├── MemberRepositoryInterface.php
│   ├── BrandRepositoryInterface.php
│   ├── CaseRepositoryInterface.php
│   ├── DiyModelRepositoryInterface.php
│   ├── DoorOrderRepositoryInterface.php
│   ├── BidOrderRepositoryInterface.php
│   ├── BidProjectRepositoryInterface.php
│   ├── FactoryInspectionRepositoryInterface.php
│   ├── ReportProjectRepositoryInterface.php
│   ├── OrderInvoiceRepositoryInterface.php
│   ├── QuoteRepositoryInterface.php
│   ├── PptRepositoryInterface.php
│   └── SpaceRepositoryInterface.php
│
├── infrastructure/       # 16 个 Repository 实现 + 基类
│   ├── BaseRepository.php               # ★ 基础 Repository (21.8KB)
│   ├── GoodsRepository.php              # 商品数据访问 (59.3KB)
│   ├── ProjectRepository.php            # 项目数据访问 (93.0KB)
│   ├── OrderRepository.php              # 订单数据访问 (82.4KB)
│   ├── MemberRepository.php             # 会员数据访问 (39.6KB)
│   ├── DiyModelRepository.php           # 云设计数据 (68.1KB)
│   ├── DoorOrderRepository.php          # 上门订单数据 (21.1KB)
│   ├── SpaceRepository.php              # 空间数据 (23.2KB)
│   ├── BidProjectRepository.php         # 投标项目数据 (18.3KB)
│   ├── BidOrderRepository.php           # 投标订单数据 (12.5KB)
│   ├── BrandRepository.php              # 品牌数据 (12.9KB)
│   ├── CaseRepository.php              # 案例数据 (6.7KB)
│   ├── FactoryInspectionRepository.php   # 工厂考察数据 (10.1KB)
│   ├── ReportProjectRepository.php      # 项目报备数据 (21.4KB)
│   ├── OrderInvoiceRepository.php       # 发票数据 (11.0KB)
│   ├── QuoteRepository.php             # 报价数据 (1.2KB)
│   ├── PptRepository.php               # PPT数据 (9.4KB)
│   └── ...（备份文件约 6 个）
│
├── models/               # 9 个 Eloquent Model
│   ├── Project.php                      # 项目/方案
│   ├── Floors.php                       # 楼层
│   ├── FloorSpace.php                   # 楼层空间
│   ├── Follow.php                       # 关注
│   ├── CaseModel.php                    # 案例
│   ├── MemberQuoteTemplate.php          # 会员报价模板
│   ├── PptTemplate.php                  # PPT 模板
│   ├── SearchGoods.php                  # 搜索商品缓存
│   └── SearchSupplier.php              # 搜索供应商缓存
│
└── listeners/            # 事件监听器（当前为空目录）
```

---

## 2. 分层架构说明

```
Controller (API 入口, 参数校验, 响应封装)
    ↓ 依赖注入
Service (业务逻辑, 无状态)
    ↓ 调用
Repository Interface (数据访问抽象)
    ↓ 实现
Infrastructure (具体数据源: DB/API/Cache/File)
    ↓ 操作
Model (Eloquent ORM, 表映射)
```

**关键规则**:
- Controller 只做参数校验和响应封装，**不写业务逻辑**
- Service 是无状态的纯业务逻辑层，通过 DI 注入
- Repository 是与数据源解耦的接口，**Infrastructure 层实现**
- 所有数据库操作必须通过 Repository → Infrastructure 链路

---

## 3. API 接口清单

> **统一约定**:
> - 成功响应: `{ result: 0, msg: "ok", data: ... }`
> - 错误响应: `{ result: -1, msg: "错误信息", data: ... }`
> - 认证方式: 大部分接口需要登录态（Token/Session），标注 `公开` 的可匿名访问

---

### 3.1 商品模块 — GoodsController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `conditions` | GET/POST | 登录 | 获取商品筛选条件（品牌/分类/尺寸等） |
| `search` | GET/POST | 登录 | 搜索商品列表 |
| `previewList` | GET/POST | 登录 | 预览项目中的商品列表 |

**请求参数**:

```
GET/POST  conditions
  无参数，返回筛选条件元数据

GET/POST  search
  | search          | string  | 搜索关键词                                  |
  | order_field     | array   | 排序字段 (可选)                              |

GET/POST  previewList
  | project_id      | int     | 必填，项目ID                                 |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getGoodsInfo` | GET/POST | 登录 | 获取商品完整详情（含3D模型/规格/品牌/推荐） |
| `getGoodsOption` | GET/POST | 登录 | 获取商品规格选项 |
| `getComment` | GET/POST | 登录 | 获取商品评价 |
| `click` | GET/POST | 登录 | 记录商品规格点击（热度统计） |

**请求参数**:

```
GET/POST  getGoodsInfo
  | goods_id        | int     | 必填，商品ID                                 |

GET/POST  getGoodsOption
  | goods_id        | int     | 必填，商品ID                                 |

GET/POST  getComment
  无参数（从上下文读取 goods_id）

GET/POST  click
  | option_id       | int     | 商品规格ID                                   |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `updateUnitPrice` | POST | 登录 | 修改商品单价（项目内） |
| `updateOption` | POST | 登录 | 修改商品规格 |
| `analysis` | POST | 登录 | CAD 图纸解析（单文件 DWG） |
| `analysisV2` | POST | 登录 | CAD 图纸解析（多文件 DWG） |

**请求参数**:

```
POST  updateUnitPrice
  | price           | numeric | 必填，新单价                                 |
  | option_ids      | array   | 必填，规格ID数组                              |
  | method          | int     | 必填，调价方式                                |
  | space_id        | int     | 必填，空间ID                                  |

POST  updateOption
  | (全部请求数据)   | object  | 规格更新数据（动态字段）                        |

POST  analysis
  | file            | file    | 必填，DWG文件 (max:90MB, mimes:dwg)           |

POST  analysisV2
  | files           | file[]  | DWG多文件上传                                 |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getCadProgress` | GET/POST | 登录 | 获取单个商品 CAD 处理进度 |
| `getBatchProgress` | GET/POST | 登录 | 批量获取 CAD 处理进度 |
| `refreshRecommendGoods` | POST | 登录 | 刷新推荐商品（V1 算法） |
| `refreshRecommendGoodsV3` | POST | 登录 | 刷新推荐商品（V3 算法） |

**请求参数**:

```
GET/POST  getCadProgress
  | goods_id        | int     | 必填，商品ID                                 |
  返回: { percentage, message, status, file_path, updated_at }

GET/POST  getBatchProgress
  | goods_ids       | int[]   | 必填，商品ID数组                              |
  返回: { [goods_id]: { percentage, status, file_path, ... } }

POST  refreshRecommendGoods
  | goods_id        | int     | 必填                                       |
  | action          | string  | "next"/"prev" 翻页 (默认 "next")            |

POST  refreshRecommendGoodsV3
  | goods_id        | int     | 必填                                       |
  | action          | string  | 操作类型                                    |
```

---

### 3.2 商品搜索 — GoodsSearchController (V2)

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `search` | GET/POST | 登录 | MeiliSearch 全文搜索 |
| `search_goods_category` | GET/POST | 登录 | 搜索商品分类 |
| `search_goods_model` | GET/POST | 登录 | 搜索商品型号 |
| `search_fuzzy` | GET/POST | 登录 | 分类/品牌模糊搜索匹配 |
| `getSearchData` | GET/POST | 登录 | 获取搜索页面初始化数据 |
| `delete` | GET/POST | 公开 | 删除搜索索引 |
| `reindexAll` | GET/POST | 公开 | 全量重建搜索索引 |
| `updateData` | GET/POST | 登录 | 更新索引数据 |

**请求参数**:

```
GET/POST  search
  | search          | object  | 搜索条件（关键词、分类、品牌、价格区间、排序等） |

GET/POST  search_goods_category
  | search          | object  | 搜索条件                                    |

GET/POST  search_goods_model
  | search          | object  | 搜索条件                                    |

GET/POST  search_fuzzy
  | title           | string  | 搜索标题关键词                               |
  | search_type     | string  | 搜索类型（category/brand）                   |

GET/POST  getSearchData
  | search_type     | string  | 搜索数据类型                                 |
```

---

### 3.3 项目方案 — ProjectController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `createProject` | POST | 登录 | 创建项目方案 |
| `editProject` | POST | 登录 | 编辑项目 |
| `updateProject` | POST | 登录 | 更新项目信息 |
| `detail` | GET/POST | 登录 | 获取项目详情 |
| `getList` | GET/POST | 登录 | 获取项目列表 |
| `getListV2` | GET/POST | 登录 | 项目列表 V2 |
| `getMyProject` | GET/POST | 登录 | 获取我的项目列表 |
| `getActiveProject` | GET/POST | 登录 | 获取当前激活的项目 |
| `switchActiveProject` | POST | 登录 | 切换激活项目 |
| `getUserProject` | GET/POST | 登录 | 获取用户是否创建过项目 |
| `copyProject` | POST | 登录 | 创建项目副本 |
| `delete` | POST | 登录 | 删除项目（软删除） |
| `realDelete` | POST | 登录 | 彻底删除项目 |
| `restore` | GET/POST | 登录 | 恢复已删除项目 |
| `updateStatus` | POST | 登录 | 更新项目状态 |

**请求参数**:

```
POST  createProject
  | (全部请求数据)   | object  | 项目属性（名称/地址/面积/平面图等动态字段）      |
  返回: { project_id }

GET/POST  detail / switchActiveProject / delete / restore
  | project_id      | int     | 必填，项目ID                                 |

GET/POST  getList / getListV2
  | search          | object  | 筛选/排序/分页条件                           |

POST  updateStatus
  | project_id      | int     | 必填                                       |
  | status          | int     | 必填，目标状态                                |

POST  updatePrice
  | project_id      | int     | 必填                                       |
  | ratio           | numeric | 必填，调价比例                                |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `createFloor` | POST | 登录 | 创建楼层 |
| `getFloors` | GET/POST | 登录 | 获取项目楼层列表 |
| `spaceSort` | POST | 登录 | 空间排序 |
| `savePlan` | POST | 登录 | 保存平面图方案 |
| `updateFloorCad` | POST | 登录 | 更新楼层 CAD 平面图 |
| `planDelete` | POST | 登录 | 删除平面图 |
| `logout` | POST | 登录 | 退出项目方案 |
| `updateSort` | POST | 登录 | 更新购物车商品排序 |
| `getProjectData` | GET/POST | 登录 | 获取空间项目数据 |
| `checkProjectName` | GET/POST | 登录 | 检查项目名称是否重复 |
| `getProvinceCity` | GET/POST | 登录 | 获取省市区数据 |

---

### 3.4 空间管理 — SpaceController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `addSpace` | POST | 登录 | 添加空间 |
| `add3DSpace` | POST | 登录 | 添加3D空间 |
| `createSpace` | POST | 登录 | 创建新空间 |
| `editSpace` | POST | 登录 | 编辑空间 |
| `delSpace` | POST | 登录 | 删除空间 |
| `delGoods` | POST | 登录 | 从空间删除商品 |
| `updateNum` | POST | 登录 | 更新空间内商品数量 |
| `getProductList` | GET/POST | 登录 | 获取空间商品列表 |
| `getProductListV2` | GET/POST | 登录 | 获取空间商品列表 V2 |
| `moveCartItem` | POST | 登录 | 移动购物车商品到其他空间 |
| `copySpace` | POST | 登录 | 复制空间 |
| `customized` | POST | 登录 | 定制商品 |
| `test` | GET/POST | 公开 | 测试接口 |

---

### 3.5 订单模块 — OrderController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `preOrder` | POST | 登录 | 预下单（获取订单预览数据） |
| `createOrder` | POST | 登录 | 创建订单（正式下单） |
| `verifyBeforeOrder` | POST | 登录 | 下单前校验（检查商品是否下架） |
| `submitOrderSuccess` | GET/POST | 登录 | 下单成功页数据 |
| `getList` | GET/POST | 登录 | 获取订单列表 |
| `detail` | GET/POST | 登录 | 订单详情 |
| `delete` | POST | 登录 | 删除订单（移入回收站） |
| `recycle` | POST | 登录 | 恢复已删除订单 |
| `getRecycleOrder` | GET/POST | 登录 | 获取回收站订单列表 |
| `getAddress` | GET/POST | 登录 | 获取收货地址 |

**请求参数**:

```
POST  preOrder / createOrder / verifyBeforeOrder
  | project_id      | int     | 必填，项目ID                                |

POST  verifyBeforeOrder 返回:
  若商品已下架: { result: -1, msg: "商品已下架", data: { offShelf, offShelfGoods } }

GET/POST  submitOrderSuccess
  | order_id        | int     | 必填                                       |

GET/POST  getList / getRecycleOrder
  | search          | object  | 筛选条件                                    |

GET/POST  detail / delete / recycle
  | id              | int     | 必填，订单ID                                 |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getOrderGoods` | GET/POST | 登录 | 获取订单商品列表 |
| `getDrawList` | GET/POST | 登录 | 获取图纸列表 |
| `batchConfirm` | POST | 登录 | 批量确认订单 |
| `getProductSchedule` | GET/POST | 登录 | 获取生产进度 |
| `getAfterSalesOrder` | GET/POST | 登录 | 获取售后订单列表 |
| `getAfterSalesOrderDetail` | GET/POST | 登录 | 售后订单详情 |
| `getLogisticsTrack` | GET/POST | 登录 | 物流轨迹查询 |
| `getInstallTrack` | GET/POST | 登录 | 安装进度查询 |
| `getRemittanceResult` | GET/POST | 登录 | 汇款结果查询 |
| `confirmSign` | POST | 登录 | 确认签收 |
| `cancelConfirm` | POST | 登录 | 取消确认 |
| `setExtraAmount` | POST | 登录 | 设置额外费用 |
| `changePrice` | POST | 登录 | 修改订单金额 |
| `getPickupPoint` | GET/POST | 登录 | 获取自提点信息 |
| `deliveryBill` | GET/POST | 登录 | 获取送货单 |
| `getPayVoucher` | GET/POST | 登录 | 获取支付凭证 |

---

### 3.6 会员/供应商 — MemberController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `storeUserProfile` | POST | 登录 | 保存用户个人资料 |
| `getMemberInfo` | GET/POST | 登录 | 获取会员信息 |
| `getIndustry` | GET/POST | 登录 | 获取行业分类树 |
| `updatePwd` | POST | 登录 | 修改密码 |
| `sendCode` | POST | 登录 | 发送短信验证码 |

**请求参数**:

```
POST  storeUserProfile
  | nickname        | string  | 必填，昵称                                  |
  | avatar          | string  | 必填，头像URL                                |
  | industry_id     | int     | 必填，行业ID                                 |
  | identity_id     | int     | 必填，身份标识                                |
  | province_id     | int     | 必填，省份                                    |
  | city_id         | int     | 必填，城市                                    |

GET/POST  getIndustry
  | id              | int     | 可选，父级ID (0=根)                           |

POST  updatePwd
  | or_password     | string  | 必填，原始密码                               |
  | password        | string  | 必填，新密码 (6-20位)                         |
  | re_password     | string  | 必填，确认密码 (must match password)           |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `applyContact` | POST | 公开 | 提交联系需求（无需登录） |
| `verifyRealName` | POST | 登录 | 个人实名认证 |
| `verifyCompany` | POST | 登录 | 企业认证 |
| `getRealNameStatus` | GET/POST | 登录 | 获取实名认证状态 |
| `getCompanyStatus` | GET/POST | 登录 | 获取企业认证状态 |

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `applyStepOne` | POST | 登录 | 供应商申请第1步（企业信息） |
| `applyStepTwo` | POST | 登录 | 供应商申请第2步（店铺信息） |
| `applyStepThree` | POST | 登录 | 供应商申请第3步（管理人信息） |
| `applySupplier` | POST | 登录 | 供应商一站式申请 |
| `applyAccountVerify` | POST | 登录 | 对公账户验证 |
| `amountVerify` | POST | 登录 | 金额验证（对公打款验证） |
| `improveShop` | POST | 登录 | 完善店铺 + 设置密码 |
| `getApplyStatus` | GET/POST | 登录 | 获取供应商申请进度 |
| `cancelApply` | POST | 登录 | 取消供应商申请 |
| `revalidation` | POST | 登录 | 重新发起对公验证 |
| `updateContacts` | POST | 登录 | 更新联系人信息 |
| `checkUser` | GET/POST | 登录 | 检查用户名是否可用 |

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getFollowSupplier` | GET/POST | 登录 | 获取关注的供应商 |
| `getFollowCase` | GET/POST | 登录 | 获取关注的案例 |
| `getFollowCaseLable` | GET/POST | 登录 | 获取案例关注标签 |
| `generateQrCode` | GET/POST | 公开 | 生成微信绑定二维码 |
| `handleCallback` | GET/POST | 公开 | 微信绑定回调处理 |
| `bindStatus` | GET/POST | 登录 | 查询微信绑定状态 |
| `checkAuthStatus` | GET/POST | 登录 | 检查微信授权状态 |
| `unbind` | POST | 登录 | 解除微信绑定 |
| `getNotifice` | GET/POST | 登录 | 获取消息通知列表 |
| `setRead` | POST | 登录 | 标记消息已读 |
| `getUnRead` | GET/POST | 登录 | 获取未读消息数 |
| `getMapSet` | GET/POST | 登录 | 获取高德地图配置 |

---

### 3.7 品牌/供应商浏览 — BrandController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `conditions` | GET/POST | 公开 | 获取品牌筛选条件 |
| `search` | GET/POST | 公开 | 搜索品牌/供应商 |
| `detail` | GET/POST | 登录 | 品牌/供应商详情 |
| `getBrandGoods` | GET/POST | 公开 | 获取品牌下的商品 |
| `getBrandOther` | GET/POST | 公开 | 获取品牌其他资料（案例/资质等） |
| `follow` | POST | 登录 | 关注/取消关注品牌 |
| `getServiceUrl` | GET/POST | 公开 | 获取客服分配链接 |

**请求参数**:

```
GET/POST  search
  | search          | object  | 搜索条件                                    |

GET/POST  detail / getBrandGoods / follow
  | supplier_id     | int     | 必填，供应商ID                               |

GET/POST  getBrandOther
  | supplier_id     | int     | 必填                                       |
  | other_type      | int     | 必填，资料类型                                |

POST  follow
  | follow_type     | int     | 必填，关注类型 (1=关注, 0=取消)                |
```

---

### 3.8 行业案例 — CaseController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getList` | GET/POST | 公开 | 获取案例列表 |
| `getCaseLable` | GET/POST | 登录 | 获取案例标签 |
| `detail` | GET/POST | 登录 | 案例详情 |
| `toggleFavorite` | POST | 登录 | 收藏/取消收藏案例 |

**请求参数**:

```
GET/POST  getList
  | search          | object  | 筛选条件                                    |

POST  toggleFavorite
  | id              | int     | 必填，案例ID                                 |
```

---

### 3.9 项目报备 — ReportProjectController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `applyFor` | POST | 登录 | 申请项目报备 |
| `getList` | GET/POST | 登录 | 报备列表 |
| `detail` | GET/POST | 登录 | 报备详情 |
| `edit` | POST | 登录 | 编辑报备 |
| `cancel` | POST | 登录 | 取消报备 |
| `getRecommendBrand` | GET/POST | 登录 | 获取推荐品牌 |
| `searchBrand` | GET/POST | 登录 | 搜索品牌 |
| `getSearchData` | GET/POST | 登录 | 获取报备搜索数据 |
| `upload` | POST | 登录 | 上传报备附件 |

**请求参数**:

```
POST  applyFor / edit (核心字段)
  | project_id      | int     | 必填                                       |
  | supplier_id     | int     | 必填，供应商ID                               |
  | name            | string  | 必填，报备名称                               |
  | province_id     | int     | 必填                                       |
  | city_id         | int     | 必填                                       |
  | district_id     | int     | 必填                                       |
  | address_detail  | string  | 必填，详细地址                               |
  | project_area    | string  | 必填，项目面积                               |
  | project_file    | mixed   | 必填，项目文件                               |
  | purchase_mode   | int     | 必填，采购方式                               |
  | need_bid_document   | int | 必填，是否需要标书                          |
  | need_factory_inspection | int | 必填，是否需要工厂考察                    |
  | project_progress  | mixed | 必填，项目进度                              |
  | budget          | mixed   | 必填，预算                                   |
  | company_name    | string  | 必填，公司名称                               |
  | contact_name    | string  | 必填，联系人                                 |
  | contact_phone   | string  | 必填，联系电话                               |
  | company_address | string  | 必填，公司地址                               |
  | company_type    | mixed   | 必填，公司类型                               |
```

---

### 3.10 投标 — BidProjectController + BidOrderController

#### BidProjectController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `applyFor` | POST | 登录 | 申请投标（含复杂条件验证） |
| `getList` | GET/POST | 登录 | 投标列表 |
| `detail` | GET/POST | 登录 | 投标详情 |
| `cancelApply` | POST | 登录 | 取消投标申请 |
| `getRecommendBrand` | GET/POST | 登录 | 推荐品牌 |
| `searchBrand` | GET/POST | 登录 | 搜索品牌 |
| `getBidInformation` | GET/POST | 登录 | 获取投标文件信息 |

#### BidOrderController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getList` | GET/POST | 登录 | 投标订单列表 |
| `getDetail` | GET/POST | 登录 | 投标订单详情 |
| `getApplyRefund` | GET/POST | 登录 | 退款申请数据 |
| `confirmSign` | POST | 登录 | 确认签收 |
| `getLogistic` | GET/POST | 登录 | 物流查询 |
| `getAfterSales` | GET/POST | 登录 | 售后列表 |

---

### 3.11 上门测量 — DoorOrderController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `reqDoor` | POST | 登录 | 申请上门测量服务 |
| `getList` | GET/POST | 登录 | 上门订单列表 |
| `getDetail` | GET/POST | 登录 | 上门订单详情 |
| `getProjectList` | GET/POST | 登录 | 获取可上门测量的项目列表 |
| `getProjectService` | GET/POST | 登录 | 获取项目关联服务 |
| `getServiceFee` | GET/POST | 登录 | 获取服务费标准 |
| `getDoorFee` | GET/POST | 登录 | 获取上门费用 |
| `addAmount` | POST | 登录 | 添加额外费用 |
| `acceptanceCheck` | POST | 登录 | 验收确认 |
| `getAfterSales` | GET/POST | 登录 | 售后列表 |

**请求参数 (reqDoor)**:

```
POST  reqDoor
  | project_id      | int     | 必填                                       |
  | province_id     | int     | 必填                                       |
  | city_id         | int     | 必填                                       |
  | district_id     | int     | 必填                                       |
  | address_detail  | string  | 必填                                       |
  | project_area    | string  | 必填                                       |
  | number          | int     | 必填，数量                                   |
  | service_id      | int     | 必填，服务类目                                |
  | service_type    | int[]   | 服务类型数组                                 |
  | door_time       | string  | 必填，期望上门时间                            |
  | contact_name    | string  | 必填，联系人                                 |
  | contact_phone   | string  | 必填，联系电话                               |
  | service_day     | int     | 必填，服务天数                               |
```

---

### 3.12 工厂考察 — FactoryInspectionController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `applyFor` | POST | 登录 | 申请工厂考察 |
| `getList` | GET/POST | 登录 | 考察申请列表 |
| `getApplyData` | GET/POST | 登录 | 获取申请所需数据 |
| `getDetail` | GET/POST | 登录 | 考察详情 |
| `delete` | POST | 登录 | 删除考察申请 |
| `cancelApply` | POST | 登录 | 取消考察申请 |

**请求参数 (applyFor — 核心字段)**:

```
POST  applyFor
  | project_id            | int     | 必填                                    |
  | supplier_id           | int     | 必填                                    |
  | name                  | string  | 必填                                    |
  | applicant_name        | string  | 必填，申请人姓名                          |
  | applicant_phone       | string  | 必填，申请人电话                          |
  | inspection_mode       | int     | 必填，考察模式 (1=代考察)                  |
  | scheduled_inspection_date | date | 必填，计划考察日期                        |
  | scheduled_arrival_date   | date | 必填，计划到达日期                        |
  | projects_visit        | mixed   | 必填，参观项目                            |
  | travel_mode           | int     | 必填，交通方式 (1=自驾, 2=接送)            |
  | need_top_leader       | int     | 可选，是否需要高层接待                      |
  | play_video            | int     | 可选，是否播放视频                         |
  | is_led                | int     | 可选，是否LED展示                          |
  | need_hotel_booking    | int     | 可选，是否需要酒店预订                      |
  | need_restaurant_booking | int   | 可选，是否需要餐厅预订                      |
  (+ 根据 inspection_mode / travel_mode / is_led 动态扩展条件字段)
```

---

### 3.13 云设计 — DiyModelController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `designList` | GET/POST | 登录 | 我的设计列表 |
| `saveDesign` | POST | 登录 | 保存设计 |
| `getDesignDetail` | GET/POST | 登录 | 获取设计详情 |
| `getPublicDesignDetail` | GET/POST | 公开 | 公开设计详情（无需登录） |
| `duplicateDesign` | POST | 登录 | 复制设计 |
| `renameDesign` | POST | 登录 | 重命名设计 |
| `deleteDesign` | POST | 登录 | 删除设计（软删除） |
| `designRecycleList` | GET/POST | 登录 | 回收站设计列表 |
| `restoreDesign` | POST | 登录 | 恢复设计 |
| `deleteDesignReal` | POST | 登录 | 彻底删除设计 |

**请求参数**:

```
GET/POST  designList
  | search          | object  | 筛选条件                                    |

POST  saveDesign
  | (全部请求数据)   | object  | 设计数据（JSON）                              |

GET/POST  getDesignDetail / getPublicDesignDetail
  | id              | int     | 设计ID                                      |

POST  duplicateDesign / restoreDesign / deleteDesignReal
  | design_id       | int     | 设计ID                                      |

POST  renameDesign
  | design_id       | int     | 设计ID                                      |
  | name            | string  | 新名称                                      |
```

---

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `seriesGoods` | GET/POST | 登录 | 获取商品系列 |
| `relatedProducts` | GET/POST | 登录 | 获取关联商品 |
| `uploadDwg` | POST | 登录 | 上传 DWG 设计文件 |
| `getSearchCategory` | GET/POST | 登录 | 获取搜索分类树 |
| `getSearchPlace` | GET/POST | 登录 | 获取搜索位置 |
| `getMyCollect` | GET/POST | 登录 | 获取我的收藏 |
| `checkOverlap` | GET/POST | 登录 | 检测设计重叠 |
| `getRecommendIndustry` | GET/POST | 登录 | 获取推荐行业 |
| `getIssueOptions` | GET/POST | 登录 | 获取问题反馈选项 |
| `submitIssue` | POST | 登录 | 提交问题反馈 |
| `getGoodsStatus` | GET/POST | 登录 | 批量获取商品状态 |
| `getPublicGoodsStatus` | GET/POST | 公开 | 公开批量获取商品状态 |

---

### 3.14 发票管理 — OrderInvoiceController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `storeTitle` | POST | 登录 | 新增发票抬头 |
| `updateTitle` | POST | 登录 | 修改发票抬头 |
| `destroyTitle` | POST | 登录 | 删除发票抬头 |
| `setDefault` | POST | 登录 | 设为默认抬头 |
| `getTitleList` | GET/POST | 登录 | 获取抬头列表 |
| `apply` | POST | 登录 | 申请开发票 |
| `updateApply` | POST | 登录 | 修改发票申请 |
| `getList` | GET/POST | 登录 | 发票列表 |
| `getDetail` | GET/POST | 登录 | 发票详情 |
| `revoke` | POST | 登录 | 撤销发票申请 |
| `getNotInvoice` | GET/POST | 登录 | 获取未开发票的订单 |

---

### 3.15 报价模板 — QuoteController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `saveTemplate` | POST | 登录 | 保存报价模板 |
| `getTemplate` | GET/POST | 登录 | 获取报价模板列表 |
| `updateData` | GET/POST | 公开 | 数据迁移工具（一次性脚本，关联商品数据迁移） |

---

### 3.16 PPT 方案书 — PptController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `getTemplate` | GET/POST | 登录 | 获取 PPT 模板列表 |
| `generatePpt` | POST | 登录 | 生成 PPT 方案书 |
| `export` | GET/POST | 登录 | 导出 PPT |
| `savePpt` | POST | 登录 | 保存 PPT |
| `delete` | POST | 登录 | 删除 PPT |

**请求参数**:

```
POST  generatePpt
  | space_ids       | int[]   | 必填，空间ID数组                              |
  | project_name    | string  | 必填，项目名称                               |
  | template_id     | int     | 必填，模板ID                                 |
  | project_id      | int     | 必填，项目ID                                 |

GET/POST  export / savePpt / delete
  | id              | int     | 必填，PPT ID                                  |

POST  savePpt
  | project_name    | string  | 项目名称                                    |
```

---

### 3.17 搜索历史 — SearchHistoryController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `index` | GET/POST | 登录 | 获取搜索历史 |
| `clear` | GET/POST | 登录 | 清除搜索历史 |

**请求参数**:

```
GET/POST  index
  | key_type        | int     | 可选，搜索类型 (默认 1)                        |

GET/POST  clear
  | key_type        | int     | 可选                                       |
  | clear_type      | string  | "all"=全部清除 / 其他=按关键词删除             |
  | keyword         | string  | 当 clear_type != "all" 时必填                 |
```

---

### 3.18 下载限流 — DownloadController

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| `check` | GET/POST | 登录 | 下载前置检测（不处理实际下载） |

**请求参数**:

```
GET/POST  check
  | goods_id        | int     | 必填，商品ID                                 |
  | file_type       | string  | 必填，文件类型: 3d | cad | atlas | color_card |

返回:
  通过: { result: 0, msg: "ok",     data: { allow: true,  retry_after: 0 } }
  拒绝: { result: 0, msg: "频繁提示", data: { allow: false, retry_after: 秒数 } }
```

**限流策略**:

| 文件类型 | 分钟限流 | 小时配额 | 维度 |
|----------|----------|----------|------|
| `3d` | 5次/分钟 | 20次/小时 | IP + GoodsID + FileType |
| `cad` | 5次/分钟 | 20次/小时 | IP + GoodsID + FileType |
| `atlas` | 10次/分钟 | 50次/小时 | IP + GoodsID + FileType |
| `color_card` | 10次/分钟 | 50次/小时 | IP + GoodsID + FileType |

---

## 4. 核心 Service 深度解析

### 4.1 GoodsBaseService (70KB, 最核心)

**文件**: [app/frontend/modules/project/services/GoodsBaseService.php](file:///e:/project/abangmishop/app/frontend/modules/project/services/GoodsBaseService.php)

主要方法：
- `getGoodsData($goodsId)` — 完整商品数据加载（基础信息/规格/3D模型/品牌/推荐等）
- `getThreeModel($goodsId)` — 3D模型数据获取
- `buildSpecTree($goodsId)` — 规格树构建与关联选项合并
- `getBrandInfo($brandId)` — 品牌信息

缓存策略：商品详情缓存 24 小时，3D 模型实时加载。

### 4.2 GoodsSearchService / GoodsSearchServiceV2

- **V1** (`GoodsSearchService`, 39.7KB) — MeiliSearch 基础搜索
- **V2** (`GoodsSearchServiceV2`, 38.8KB) — MeiliSearch 增强搜索，当前主力版本

主要方法：
- `search($search)` — 全文搜索 + 多维度筛选 + 排序 + 分页
- `reindexAll()` — 全量重建搜索索引
- `deleteIndex($name)` — 删除索引
- `search_goods_category($search)` / `search_goods_model($search)` — 按分类/型号搜索
- `getSearchData($search_type)` — 搜索页初始数据

### 4.3 DownloadLimitService (11.1KB)

**文件**: [app/frontend/modules/project/services/DownloadLimitService.php](file:///e:/project/abangmishop/app/frontend/modules/project/services/DownloadLimitService.php)

三层防护：
- Layer 1: IP + GoodsID + FileType 分钟级限流 (60s TTL)
- Layer 2: IP + GoodsID + FileType 小时级配额 (3600s TTL)
- Layer 3: IP 全局黑名单

### 4.4 GoodsComparator (20.8KB)

商品对比服务，用于多个商品之间的参数/规格/属性对比展示。

### 4.5 CategoryRecService (28.2KB) + V3 (21.2KB)

商品推荐算法服务，基于分类/规则/点击等多维度生成推荐商品列表。

---

## 5. Repository 与 Infrastructure 对应关系

| Repository Interface | Infrastructure 实现 | 数据量 | 主要职责 |
|---------------------|--------------------|--------|----------|
| `GoodsRepositoryInterface` | `GoodsRepository` | 59.3KB | 商品数据访问、CAD 解析、批量操作 |
| `ProjectRepositoryInterface` | `ProjectRepository` | 93.0KB | ★ 最大文件，项目/楼层/空间完整 CRUD |
| `OrderRepositoryInterface` | `OrderRepository` | 82.4KB | 订单/售后/物流全流程数据 |
| `MemberRepositoryInterface` | `MemberRepository` | 39.6KB | 会员/供应商/认证数据 |
| `DiyModelRepositoryInterface` | `DiyModelRepository` | 68.1KB | 云设计/收藏数据 |
| `DoorOrderRepositoryInterface` | `DoorOrderRepository` | 21.1KB | 上门测量订单 |
| `SpaceRepositoryInterface` | `SpaceRepository` | 23.2KB | 空间/商品关联 |
| `BidProjectRepositoryInterface` | `BidProjectRepository` | 18.3KB | 投标项目 |
| `BidOrderRepositoryInterface` | `BidOrderRepository` | 12.5KB | 投标订单 |
| `BrandRepositoryInterface` | `BrandRepository` | 12.9KB | 品牌/供应商 |
| `CaseRepositoryInterface` | `CaseRepository` | 6.7KB | 案例 |
| `FactoryInspectionRepositoryInterface` | `FactoryInspectionRepository` | 10.1KB | 工厂考察 |
| `ReportProjectRepositoryInterface` | `ReportProjectRepository` | 21.4KB | 项目报备 |
| `OrderInvoiceRepositoryInterface` | `OrderInvoiceRepository` | 11.0KB | 发票 |
| `QuoteRepositoryInterface` | `QuoteRepository` | 1.2KB | 报价模板 |
| `PptRepositoryInterface` | `PptRepository` | 9.4KB | PPT 方案书 |

所有 Infrastructure 实现继承自 `BaseRepository` (21.8KB)，提供通用 CRUD、分页、事务等基类能力。

---

## 6. Model 清单

| Model | 表名 | 用途 |
|-------|------|------|
| `Project` | `ims_yz_project` | 项目/方案主表 |
| `Floors` | `ims_yz_floors` | 楼层表（关联 project_id） |
| `FloorSpace` | `ims_yz_floor_space` | 楼层空间表（关联 floor_id） |
| `Follow` | `ims_yz_follow` | 关注表（供应商/案例关注） |
| `CaseModel` | `ims_yz_case` | 行业案例表 |
| `MemberQuoteTemplate` | `ims_yz_member_quote_template` | 会员报价模板 |
| `PptTemplate` | `ims_yz_ppt_template` | PPT 模板表 |
| `SearchGoods` | `ims_yz_search_goods` | 搜索商品缓存 |
| `SearchSupplier` | `ims_yz_search_supplier` | 搜索供应商缓存 |

---

## 7. 注意事项

### 7.1 备份文件管理

services/ 和 infrastructure/ 目录中存在大量以 `copy`、日期后缀命名的备份文件（约 20+ 个），建议清理归档，仅保留当前生效版本。



### 7.3 Search 双版本

存在两个搜索 Service (`GoodsSearchService` V1 + `GoodsSearchServiceV2` V2) 和对应的两个 Controller (`GoodsSearchController` + `GoodsSearchTestController`)。当前主力使用 V2，V1 及 TestController 可能为过渡版本。

### 7.4 认证白名单

部分接口声明了 `$publicAction` / `$ignoreAction`（无需登录），包括：
- `DownloadController::check` 以外的下载相关
- `CaseController::getList`
- `BrandController::getBrandOther, conditions, getServiceUrl, getBrandGoods`
- `MemberController::applyContact, handleCallback, generateQrCode`
- `DiyModelController::getPublicDesignDetail, getPublicGoodsStatus`
- `GoodsSearchController::reindexAll, delete`

### 7.5 事务保护

部分控制器声明了 `$transactionActions`，表示对应方法在数据库事务中执行：
- `MemberController: applySupplier, improveShop`
- `BidProjectController: applyFor`
- `BidOrderController: applyFor`
- `DoorOrderController: reqDoor`
- `OrderInvoiceController: apply`

---

*文档基于 app/frontend/modules/project/ 源码完整分析生成 | 2026-05-27*
