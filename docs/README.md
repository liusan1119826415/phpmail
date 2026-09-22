# docs 目录交接说明

> **最后更新**: 2026-05-27
> **用途**: 帮助新成员快速了解 docs 目录下每份文件的作用、适用场景和维护方式。

---

## 文件清单总览

| 文件 | 大小 | 类型 | 适用人群 | 用途 |
|------|------|------|----------|------|
| [repo_wiki.md](#1-repo_wikimd) | 26.6KB | 项目百科 | 全员 | 项目概述、技术栈、模块功能速查 |
| [deployment_and_config.md](#2-deployment_and_configmd) | 19.0KB | 部署运维 | 运维 / 后端 | 部署步骤、服务器账号、Nginx 配置、运维命令 |
| [architecture_design.md](#3-architecture_designmd) | 53.9KB | 技术架构 | 后端开发 | 架构全景、请求链路、框架定制、分层设计、扩展机制 |
| [project_module_handover.md](#4-project_module_handovermd) | 5.2KB | 模块交接 | 接手该模块的开发者 | Project 3D 模块的功能说明、API、限流策略 |
| [database_document.html](#5-database_documenthtml) | 5169.9KB | 数据库可视化 | 全员 | 数据库表结构可视化 ER 图 (HTML 交互式) |
| [整体系统架构图.mermaid](#6-整体系统架构图mermaid) | 2.3KB | 架构图源码 | 技术负责人 | 系统架构 Mermaid 源文件 (可编辑、可呈现) |
| [整个系统架构图.png](#7-整个系统架构图png) | 1220.9KB | 架构图图片 | 全员 | 系统架构静态预览图 (从 .mermaid 导出) |

---

## 1. repo_wiki.md

### 是什么
项目仓库 Wiki，一份面向全团队的项目概览文档。

### 包含内容
- **项目概述**: 定位、基本信息表 (名称/框架/PHP版本/时区/语言等)
- **技术栈**: 后端 (Laravel/MySQL/Redis/MeiliSearch/EasyWeChat等) + 前端 (Mix/Three.js/Tween.js) + 微擎集成
- **系统架构**: Mermaid 架构图 + 分层架构 ASCII 图 + Service Provider 体系
- **目录结构**: 完整目录树，标注每层职责
- **核心功能模块**: 商品系统、3D可视化 (Project)、订单系统、会员系统、营销系统
- **插件系统**: 100+ 插件分类列表
- **支付系统**: 50+ 支付通道一览 + 支付流程图
- **异步任务队列**: 67 个 Job 分类与用途
- **数据库与缓存**: MySQL/MongoDB/Redis/搜索引擎配置
- **路由体系**: 8 个路由文件 + 11 个微擎入口文件
- **开发指南**: 环境要求、快速开始、代码规范、常用命令
- **部署与运维**: Supervisor 配置、常见问题排查、第三方服务依赖、配置文件索引

### 适用场景
- 新人入职第一天，建立对项目的全局认识
- 快速查找某功能属于哪个模块
- 了解项目规模和依赖清单

### 维护方式
- 新增重要模块或插件时，在对应章节补充
- 版本升级时更新技术栈表格
- 原则上不在此文档中写入敏感配置信息 (敏感内容放在 deployment_and_config.md)

---

## 2. deployment_and_config.md

### 是什么
面向运维和部署人员，包含全部服务器连接信息、配置文件内容和标准化部署流程。

### 包含内容
- **代码仓库**信息
- **服务器环境**: 运行模式、Web 入口、数据库配置 (主库/从库/客服库，含具体地址和账号)
- **Redis/MongoDB/MeiliSearch** 地址和端口
- **系统地址与账号**: 域名、Cookie 域名、邮件 SMTP、第三方密钥、APP_KEY
- **系统架构**: Mermaid 整体架构图 + 目录架构 + 入口请求流程图 + 多入口体系
- **部署文档**: 环境要求、6 步部署流程 (环境准备→代码部署→.env配置→Nginx→服务启动→Supervisor)
- **系统运维**: 常用命令、日志位置、故障排查、Redis Key 命名规范
- **配置优先级**: `.env` > `database/config.php` > `config/*.php` > 数据库 `ims_yz_setting` 表

### 适用场景
- 服务器迁移或新环境搭建时逐步执行
- 排查支付回调失败、搜索无结果等故障
- 管理员忘记某个服务的连接地址时查阅

### 维护方式
- 服务器信息变更后**立即更新**
- 新增第三方服务时补充密钥信息
- **注意**: 文件包含明文密码，需控制访问权限

---

## 3. architecture_design.md

### 是什么
深度技术架构设计文档，面向后端开发者的"系统设计说明书"。

### 包含内容 (16 个章节 + 2 个附录)

| 章节 | 内容 |
|------|------|
| 1. 系统概述与架构全景 | 定位、核心能力矩阵、完整 Mermaid 架构图 |
| 2. 请求生命周期 | HTTP → Nginx → Application → Kernel → 中间件 → 路由 → 控制器 全链路 |
| 3. 框架扩展层 | 自定义 Application、日志系统、数据库层、Redis、Queue、Bus、Repository |
| 4. 服务提供者体系 | 14 个 Provider 职责 + ShopProvider 18 个 Manager 绑定详解 |
| 5. 中间件链路 | 全局/路由组/路由级三层中间件 + 10 个认证/功能中间件详解 |
| 6. 路由体系 | 8 个路由文件 + Platform vs 微擎模式 + URL 命名约定 |
| 7. 分层架构 | Controller/Service/Repository/Infrastructure/Model 五层 + 实际代码示例 |
| 8. 插件架构 | 目录规范、SPL 自动加载、生命周期、100+ 插件分类 |
| 9. 支付架构 | 通道注册、回调流程、安全机制、50+ 通道一览 |
| 10. 事件驱动架构 | $listen + $subscribe 双机制、200+ 事件分类 |
| 11. 异步队列 | 67 个 Job、Redis Queue + Workerman、Supervisor 配置 |
| 12. 数据架构 | MySQL/MongoDB/Redis/MeiliSearch 完整说明 |
| **13. 多租户架构** | **⚠️ 已移除，内容待补充** |
| 14. 前端架构 | Laravel Mix、Three.js + Tween.js、微擎前端约定 |
| 15. 安全架构 | 认证体系、限流、CSRF、支付白名单 |
| 16. 扩展点与定制化 | ModelExpansion、Blade 过滤器、Eventy、Manager 扩展 |
| 附录 A | 关键文件快速索引 |
| 附录 B | 与其他文档的关系说明 |

### 适用场景
- 接手后端开发，需要理解系统设计决策和扩展机制
- 需要新增插件或支付通道时参考
- 技术方案评审时的参考依据
- 排查复杂 Bug 时理解调用链路

### 维护方式
- 架构变更 (如新增中间件、调整 Provider 注册) 后同步更新
- **注意**: 第 13 章"多租户架构"最近被移除，需要手动恢复或重写

---

## 4. project_module_handover.md

### 是什么
针对 `app/frontend/modules/project/` (3D 可视化商品模块) 的专项交接文档。

### 包含内容
- **模块概述**: 定位与职责
- **目录结构**: Controller / Infrastructure / Listener / Model / Repository / Service
- **核心功能详解**:
  - 商品详情与 3D 模型 (GoodsBaseService → `getGoodsData()` / `getThreeModel()`)
  - 商品搜索 (GoodsSearchService → MeiliSearch 多维度筛选)
  - CAD 图纸解析 (GoodsRepository → `analysis()` / `analysisV2()`)
  - 下载限流 (DownloadLimitService → IP+GoodsID+FileType 三级限流)
- **技术架构**: 分层架构 + 关键技术栈
- **部署与运维**: 环境要求、关键配置文件、常见问题处理
- **维护指南**: 代码规范、测试策略、版本控制
- **联系方式**: 技术负责人/运维/产品对接 (待填写)

### 适用场景
- 新同事接手 Project 模块的专属阅读材料
- 排查该模块相关问题时的参考

### 维护方式
- 模块核心逻辑变更时更新对应章节
- 联系方式部分需要填写具体人员信息

---

## 5. database_document.html

### 是什么
数据库表结构的**可视化交互式文档** (HTML 格式)。

### 包含内容
- 数据库中所有表的 ER (实体关系) 图
- 表结构字段说明
- 表之间的关联关系可视化

### 适用场景
- 了解某个业务数据存储在哪些表中
- 查看表结构和字段含义
- 理解表之间的外键和关联关系

### 维护方式
- 数据库新增表或变更字段后重新导出
- 文件较大 (5MB+)，建议通过 Web 服务器访问而非直接在编辑器中打开

---

## 6. 整体系统架构图.mermaid

### 是什么
系统整体架构图的 **Mermaid 源码**，描述客户端 → 网关 → 前端 → 后端 → 数据层的完整拓扑。

### 包含的架构层级
```
客户端 (用户/后台/客服)
  → 网关层 (Nginx)
    → 前端层 (SSR 渲染)
    → 后端层 (PHP-FPM + 定时任务)
      → 搜索服务 (MeiliSearch)
      → 客服系统 (WebSocket)
      → 业务服务层 (以图搜图/CAD解析/3D检测)
    → 存储层 (主库/客服库/OSS/Redis)
```

### 适用场景
- 用支持 Mermaid 的工具 (VS Code + 插件 / Typora / GitHub) 查看渲染效果
- 需要修改架构图时编辑此文件，再重新导出为 PNG

### 维护方式
- 新增服务节点或调整架构时修改此文件
- 修改后同步更新 [整个系统架构图.png](#7-整个系统架构图png)

---

## 7. 整个系统架构图.png

### 是什么
[整体系统架构图.mermaid](#6-整体系统架构图mermaid) 的 **PNG 静态截图**，方便在不支持 Mermaid 的环境下查看。

### 适用场景
- 嵌入 PPT 或 Word 文档
- 发送给非技术人员查看
- 快速预览架构而不需要 Mermaid 渲染环境

### 维护方式
- 从 `.mermaid` 文件导出，保持与源文件一致
- 架构变更时需要重新导出覆盖

---

## 文档之间的关系

```
┌──────────────────────────────────────────────────────────────┐
│                      新人阅读顺序                               │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  第一步: repo_wiki.md           了解项目是什么                  │
│     ↓                                                        │
│  第二步: 整个系统架构图.png      获取架构全局印象                │
│     ↓                                                        │
│  第三步: architecture_design.md  深入理解技术架构               │
│     ↓                                                        │
│  第三步: deployment_and_config.md  (运维人员在部署时查看)        │
│     ↓                                                        │
│  第四步: project_module_handover.md  (按需，接手模块时查看)      │
│     ↓                                                        │
│  第五步: database_document.html     (按需，查表结构时查看)       │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

| 文档 | 覆盖范围 | 重要程度 |
|------|----------|----------|
| `repo_wiki.md` | 全景概览 | 入门必读 |
| `architecture_design.md` | 技术深度 | 后端开发必读 |
| `deployment_and_config.md` | 基础设施 | 运维部署必读 |
| `project_module_handover.md` | 单个模块 | 模块接手者必读 |
| `整个系统架构图.png` + `.mermaid` | 架构可视化 | 全员推荐 |
| `database_document.html` | 数据模型 | 按需查阅 |

---

*文档整理于 2026-05-27*
