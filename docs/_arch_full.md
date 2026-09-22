1	# 芸众商城 (abangmishop) — 架构设计文档
2	
3	> **版本**: 主版本 2.3.359 | 后台 2.1.874 | 前端 2.2.988 | 商家端 1.1.113
4	> **框架**: Laravel 8.83.27 + 微擎 (WeEngine)
5	> **最后更新**: 2026-05-27
6	> **文档性质**: 技术架构设计，面向开发交接与新成员上手
7	
8	---
9	
10	## 1. 系统概述与架构全景
11	
12	### 1.1 项目定位
13	
14	芸众商城 (abangmishop) 是一个基于 **Laravel 8 + 微擎 (WeEngine)** 框架构建的大型 SaaS 电商中台系统，以 **"插件化架构 + 多渠道支付 + 3D可视化"** 为技术特色，提供覆盖 B2C 商城、分销裂变、社区营销、直播带货、门店 POS 等全链路电商解决方案。
15	
16	### 1.2 核心能力矩阵
17	
18	| 能力维度 | 技术实现 | 规模数据 |
19	|----------|----------|----------|
20	| **多端入口** | index.php / api.php / shop.php / admin.html / module.php / processor.php | 11 个入口文件 |
21	| **插件体系** | 统一目录规范 + SPL 自动加载 + 生命周期管理 | 100+ 插件 |
22	| **支付通道** | 统一 PaymentServiceProvider + 通道注册 | 50+ 支付通道 |
23	| **事件驱动** | Laravel Event + Listener + Subscriber | 200+ 事件定义, 50+ 监听器 |
24	| **异步队列** | Redis Queue + Workerman MQTT | 67 个 Job 类 |
25	| **3D 可视化** | Three.js 0.171.0 + Tween.js 18.6.4 | 模型预览/交互动画/CAD 解析 |
26	| **搜索引擎** | MeiliSearch / Elasticsearch 双引擎 | 商品全文检索 |
27	| **数据存储** | MySQL + MongoDB + Redis 混合架构 | 665 个迁移文件 |
28	
29	### 1.3 架构全景图
30	
31	```mermaid
32	graph TB
33	    subgraph 接入层
34	        NGINX[Nginx/Apache]
35	    end
36	
37	    subgraph 入口分发层
38	        IDX[index.php 主入口]
39	        API_MICRO[api.php 微擎入口]
40	        SHOP[shop.php 商城入口]
41	        MODULE[module.php 模块入口]
42	        PAY_ENTRY[payment/ 支付入口]
43	        CRON[cron.php 定时任务]
44	    end
45	
46	    subgraph 框架引导层
47	        LARAVEL_PHP[app/laravel.php]
48	        APP_INSTANCE[app/framework/Foundation/Application]
49	        KERNEL[app/Kernel.php]
50	        YUNSHOP[app/yunshop.php<br/>YunShop/YunApp/YunPlugin/YunNotice]
51	    end
52	
53	    subgraph 中间件层
54	        GLOBAL_MW[全局: Maintenance / ShopRoute]
55	        ADMIN_MW[admin组: Install/Session/Auth]
56	        API_MW[api组: throttle]
57	    end
58	
59	    subgraph 路由与控制器层
60	        ROUTES[8 个路由文件]
61	        BACKEND_CTRL[backend/ 后台控制器]
62	        FRONTEND_CTRL[frontend/ 前端API控制器]
63	        PLATFORM_CTRL[platform/ 平台控制器]
64	        OUTSIDE_CTRL[outside/ 外部接口控制器]
65	        BUSINESS_CTRL[business/ 商家端控制器]
66	    end
67	
68	    subgraph 服务层
69	        SERVICES[app/common/services/<br/>92个公共服务类]
70	        FRONTEND_SVC[app/frontend/modules/<br/>30个前端业务模块]
71	    end
72	
73	    subgraph 数据访问层
74	        REPOS[Repositories 仓储接口]
75	        INFRA[Infrastructure 数据源实现]
76	        MODELS[Models / Eloquent 模型]
77	    end
78	
79	    subgraph 横向支撑层
80	        EVENTS[事件系统: 200+ Event]
81	        LISTENERS[监听器: 50+ Listener]
82	        JOBS[异步队列: 67个 Job]
83	        PLUGINS[插件系统: 100+ Plugin]
84	        PAYMENTS[支付系统: 50+ 通道]
85	    end
86	
87	    subgraph 数据层
88	        MYSQL[(MySQL: abangmi_com)]
89	        MONGO[(MongoDB)]
90	        REDIS[(Redis: 缓存/队列/会话)]
91	        SEARCH[(MeiliSearch/ES)]
92	    end
93	
94	    NGINX --> IDX
95	    NGINX --> API_MICRO
96	    NGINX --> SHOP
97	    NGINX --> MODULE
98	    NGINX --> PAY_ENTRY
99	    NGINX --> CRON
100	
101	    IDX --> LARAVEL_PHP
102	    API_MICRO --> LARAVEL_PHP
103	    SHOP --> LARAVEL_PHP
104	    MODULE --> LARAVEL_PHP
105	    PAY_ENTRY --> LARAVEL_PHP
106	    CRON --> LARAVEL_PHP
107	
108	    LARAVEL_PHP --> APP_INSTANCE
109	    APP_INSTANCE --> KERNEL
110	    KERNEL --> YUNSHOP
111	    KERNEL --> GLOBAL_MW
112	    GLOBAL_MW --> ADMIN_MW
113	    GLOBAL_MW --> API_MW
114	    ADMIN_MW --> ROUTES
115	    API_MW --> ROUTES
116	
117	    ROUTES --> BACKEND_CTRL
118	    ROUTES --> FRONTEND_CTRL
119	    ROUTES --> PLATFORM_CTRL
120	    ROUTES --> OUTSIDE_CTRL
121	    ROUTES --> BUSINESS_CTRL
122	
123	    BACKEND_CTRL --> SERVICES
124	    FRONTEND_CTRL --> SERVICES
125	    FRONTEND_CTRL --> FRONTEND_SVC
126	    SERVICES --> REPOS
127	    FRONTEND_SVC --> REPOS
128	    REPOS --> INFRA
129	    INFRA --> MODELS
130	
131	    MODELS --> MYSQL
132	    MODELS --> MONGO
133	    SERVICES --> REDIS
134	    SERVICES --> SEARCH
135	
136	    BACKEND_CTRL --> EVENTS
137	    FRONTEND_CTRL --> EVENTS
138	    EVENTS --> LISTENERS
139	    LISTENERS --> JOBS
140	    JOBS --> REDIS
141	
142	    BACKEND_CTRL --> PLUGINS
143	    BACKEND_CTRL --> PAYMENTS
144	    PLUGINS --> MYSQL
145	    PAYMENTS --> MYSQL
146	```
147	
148	---
149	
150	## 2. 请求生命周期详解
151	
152	### 2.1 完整调用链
153	
154	```
155	HTTP 请求
156	  │
157	  ├─ Nginx/Apache 接收 → 根据 URL 分发到入口文件
158	  │
159	  ├─ [入口文件] index.php / api.php / shop.php 等
160	  │    ├─ 定义 LARAVEL_START 微秒时间戳
161	  │    ├─ require vendor/autoload.php (Composer 自动加载)
162	  │    └─ require bootstrap/app.php → 创建 Application 实例
163	  │
164	  ├─ [bootstrap/app.php]
165	  │    ├─ new app\framework\Foundation\Application() — 自定义 Application
166	  │    ├─ 绑定核心接口:
167	  │    │    ├─ Http\Kernel → app\Kernel
168	  │    │    ├─ Console\Kernel → app\console\Kernel
169	  │    │    ├─ ExceptionHandler → app\common\exceptions\Handler
170	  │    │    └─ Dispatcher → app\framework\Bus\Dispatcher
171	  │    └─ 注册自定义日志: TraceLog / DebugLog / ErrorLog
172	  │
173	  ├─ [app/laravel.php]
174	  │    ├─ $kernel = $app->make(HttpKernel::class)
175	  │    ├─ $request = app\framework\Http\Request::capture()
176	  │    ├─ $response = $kernel->handle($request)  ← 核心处理
177	  │    └─ $response->send()
178	  │
179	  ├─ [Kernel::handle() → sendRequestThroughRouter()]
180	  │    ├─ 全局中间件: CheckForMaintenanceMode → ShopRoute
181	  │    ├─ 路由组中间件 (根据路径前缀匹配):
182	  │    │    ├─ /admin → Install → Session → Auth → ...
183	  │    │    ├─ /api  → throttle:60,1
184	  │    │    └─ /     → web (空组)
185	  │    ├─ 路由匹配: RouteServiceProvider::map()
186	  │    ├─ 控制器中间件 (内部 Pipeline)
187	  │    └─ 控制器方法调用
188	  │
189	  ├─ [控制器 → 服务层 → 仓储层 → 数据源]
190	  │    └─ 业务流程处理 + 事件触发
191	  │
192	  └─ $kernel->terminate($request, $response)
193	       └─ 收尾工作: 日志写入、Session 存储、队列 Job 提交等
194	```
195	
196	### 2.2 入口分发机制
197	
198	项目运行模式由 `.env` 中的 `APP_Framework` 控制：
199	
200	| 配置值 | 模式 | 说明 |
201	|--------|------|------|
202	| `platform` | 独立平台模式 | 使用 `.env` 配置，不依赖微擎 `data/config.php` |
203	| 其他值 | 微擎模式 | 从微擎 `data/config.php` 动态加载数据库配置 |
204	
205	### 2.3 多入口文件与路由对应
206	
207	| 入口文件 | 典型 URL | 路由文件 | 中间件组 |
208	|----------|----------|----------|----------|
209	| `index.php` | `/` | `routes/web.php` | `web` |
210	| `index.php` (admin路径) | `/admin/*` | `routes/admin.php` + `routes/shop.php` | `admin` |
211	| `index.php` (business路径) | `/business/*` | `routes/business.php` | (自定义) |
212	| `index.php` (outside路径) | `/outside/*` | `routes/outside.php` | `web` |
213	| `api.php` | `/addons/yun_shop/api.php` | 微擎模式路由 | - |
214	| `shop.php` | `/shop.php` | 商城独立路由 | - |
215	| `cron.php` | `/cron.php` | Artisan schedule | - |
216	| `payment/{channel}/notifyUrl.php` | 支付回调 | 各通道独立处理 | 无 |
217	
218	### 2.4 YunShop 核心类
219	
220	`app/yunshop.php` 是项目最核心的基础设施文件，被 Composer 作为 `files` 自动加载，定义了四个关键类：
221	
222	| 类 | 职责 | 关键方法 |
223	|----|------|----------|
224	| `YunShop` | 全局工具类，入口判断 | `isWeb()`, `isApp()`, `isApi()`, `isPlugin()`, `app()`, `plugin()` |
225	| `YunApp` (继承 YunComponent) | 应用上下文，存储 `$_W` 全局变量 | `getMemberId()` (多端 Token 解析), `__get('uniacid')` |
226	| `YunPlugin` | 插件状态检测 | `get($key)` → `app('plugins')->isEnabled($key)` |
227	| `YunNotice` | 消息通知开关判断 | `getNotSend($routes)` |
228	
229	`YunShop::app()->getMemberId()` 支持 5 种以上 Token 类型（原生 App type=9、主播 App type=14、CPS App type=15、POS、直播安装等），从 Session/Cookie/Redis/Header 多来源解析会员 ID。
230	
231	---
232	
233	## 3. 框架扩展层 (`app/framework/`)
234	
235	项目在 Laravel 基础上进行了深层定制，`app/framework/` 目录包含对核心组件的扩展实现。
236	
237	### 3.1 自定义 Application
238	
239	**文件**: [app/framework/Foundation/Application.php](file:///e:/project/abangmishop/app/framework/Foundation/Application.php)
240	
241	```php
242	class Application extends \Illuminate\Foundation\Application
243	{
244	    // 重写基础服务注册，用自己的 EventServiceProvider 替换 Laravel 原生
245	    protected function registerBaseServiceProviders() {
246	        $this->register(new EventServiceProvider($this));
247	        $this->register(new RoutingServiceProvider($this));
248	        $this->register(new LogServiceProvider($this));
249	    }
250	
251	    // 新增路径方法
252	    public function getRoutesPath($file = null);      // routes/ 目录
253	    public function getFrontendPath();                  // app/frontend/
254	    public function getBackendPath();                   // app/backend/
255	    public function getPluginsPath();                   // plugins/
256	    public function getPaymentPath();                   // payment/
257	}
258	```
259	
260	### 3.2 自定义日志系统
261	
262	**目录**: [app/framework/Log/](file:///e:/project/abangmishop/app/framework/Log/)
263	
264	| 日志类 | Singleton Key | 用途 |
265	|--------|---------------|------|
266	| `TraceLog` | `Log.trace` | 请求追踪与性能分析日志 |
267	| `DebugLog` | `Log.debug` | 开发调试日志 |
268	| `ErrorLog` | `Log.error` | 错误与异常日志 |
269	| `CronLog` | (动态创建) | 定时任务执行日志 |
270	| `SqlLog` | (动态创建) | SQL 查询日志 |
271	
272	全部在 `bootstrap/app.php` 中以 singleton 方式注册到 IoC 容器。
273	
274	### 3.3 自定义数据库层
275	
276	**目录**: [app/framework/Database/](file:///e:/project/abangmishop/app/framework/Database/)
277	
278	| 组件 | 扩展点 |
279	|------|--------|
280	| `DatabaseServiceProvider` | 替换 Laravel 原生数据库服务提供者 |
281	| `DatabaseManager` | 扩展 `Illuminate\Database\DatabaseManager` |
282	| `MySqlConnection` | 自定义 MySQL 连接，继承 `Illuminate\Database\MySqlConnection` |
283	| `Eloquent/Model.php` | 自定义基础 Model 类 |
284	| `Eloquent/Builder.php` | 扩展 Query Builder |
285	
286	**关键行为**: `AppServiceProvider` 中全局设置 `PDO::FETCH_ASSOC` 提取模式：
287	
288	```php
289	\Event::listen(StatementPrepared::class, function ($event) {
290	    $event->statement->setFetchMode(\PDO::FETCH_ASSOC);
291	});
292	```
293	
294	这意味着所有数据库查询结果默认返回关联数组而非对象。
295	
296	### 3.4 自定义 Redis
297	
298	**目录**: [app/framework/Redis/](file:///e:/project/abangmishop/app/framework/Redis/)
299	
300	扩展 Redis 连接与驱动，支持 Predis 客户端。项目配置了两个 Redis 连接：
301	
302	| 连接名 | 用途 | 数据库 |
303	|--------|------|--------|
304	| `default` | 默认缓存、Session、队列 | db 0 |
305	| `cache` | 独立缓存数据库 | db 1 |
306	
307	### 3.5 自定义 Queue
308	
309	扩展 Redis Queue 驱动，连接项目自定义的 Redis 连接管理。
310	
311	### 3.6 自定义 Bus (命令总线)
312	
313	绑定自定义 `Dispatcher` 到 `Illuminate\Contracts\Bus\Dispatcher` 接口，扩展命令/Job 分发机制。
314	
315	### 3.7 Repository 抽象
316	
317	**目录**: [app/framework/Repository/](file:///e:/project/abangmishop/app/framework/Repository/)
318	
319	提供基础 Repository 模式支持，包括：
320	- 基础 Repository 接口定义
321	- Eloquent Repository 实现
322	- Criteria 模式支持（条件筛选封装）
323	- Scope 模式支持（查询作用域）
324	
325	---
326	
327	## 4. 服务提供者体系
328	
329	项目通过 14 个 ServiceProvider 组织核心服务，负责 IoC 绑定、中间件注册、路由加载、事件监听等职责。
330	
331	### 4.1 Provider 注册清单
332	
333	| Provider | 文件 | 核心职责 |
334	|----------|------|----------|
335	| `AppServiceProvider` | [app/common/providers/AppServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/AppServiceProvider.php) | PDO 模式设置、安装验证、Blade 指令扩展、UniAcid 注入 |
336	| `YunShopServiceProvider` | [app/common/providers/YunShopServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/YunShopServiceProvider.php) | `$_W` 全局变量构建、微擎兼容层、远程附件 URL 配置 |
337	| `ShopProvider` | [app/common/providers/ShopProvider.php](file:///e:/project/abangmishop/app/common/providers/ShopProvider.php) | **最核心的 Provider**，注册 15+ 个业务 Manager 单例 |
338	| `PluginServiceProvider` | [app/common/providers/PluginServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/PluginServiceProvider.php) | 插件自动加载、命名空间注册、生命周期回调 |
339	| `EventServiceProvider` | [app/common/providers/EventServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/EventServiceProvider.php) | 100+ Event-Listener 映射 + 20+ Subscriber 注册 |
340	| `RouteServiceProvider` | [app/common/providers/RouteServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/RouteServiceProvider.php) | 多模式路由分组加载 |
341	| `PaymentServiceProvider` | [app/common/providers/PaymentServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/PaymentServiceProvider.php) | 支付通道统一注册 |
342	| `CronServiceProvider` | [app/common/providers/CronServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/CronServiceProvider.php) | 定时任务管理 |
343	| `ExportServiceProvider` | [app/common/providers/ExportServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/ExportServiceProvider.php) | 数据导出服务 |
344	| `ProjectServiceProvider` | [app/common/providers/ProjectServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/ProjectServiceProvider.php) | Project 3D 模块服务注册 |
345	| `BroadcastServiceProvider` | [app/common/providers/BroadcastServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/BroadcastServiceProvider.php) | 广播频道授权 |
346	| `BusServiceProvider` | [app/common/providers/BusServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/BusServiceProvider.php) | 命令总线 |
347	| `QueueServiceProvider` | [app/common/providers/QueueServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/QueueServiceProvider.php) | 队列连接 |
348	| `WeiQingServiceProvider` | [app/common/providers/WeiQingServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/WeiQingServiceProvider.php) | 微擎兼容 |
349	
350	### 4.2 ShopProvider 详解 — 核心业务 Manager 容器
351	
352	`ShopProvider::register()` 是项目核心的 IoC 绑定点，注册了以下 singleton：
353	
354	| 绑定 Key | 实现类 | 用途 |
355	|----------|--------|------|
356	| `SettingCache` | `SettingCache` | 系统设置缓存 |
357	| `supervisor` | `Supervisor` | Supervisor 进程管理客户端 |
358	| `ModelExpansionManager` | `ModelExpansionManager` | 模型动态扩展管理 |
359	| `CoinManager` | `CoinManager` | 虚拟币/积分体系管理 |
360	| `DeductionManager` | `DeductionManager` | 抵扣规则管理 |
361	| `GoodsManager` | `GoodsManager` | 商品管理 |
362	| `OrderManager` | `OrderManager` | 订单生命周期管理 |
363	| `GoodsWidgetContainer` | `GoodsWidgetContainer` | 后台商品挂件容器 |
364	| `CartContainer` | `CartContainer` | 购物车容器 |
365	| `StatusContainer` | `StatusContainer` | 订单状态流转管理 |
366	| `express` | `KDN` | 快递鸟物流查询 |
367	| `logistics` | `Logistics` | 物流服务 |
368	| `sms` | `SmsService` | 短信服务 |
369	| `GoodsDetail` | `GoodsDetailManager` | 商品详情管理 |
370	| `MemberCenter` | `MemberCenterManage` | 会员中心功能管理 |
371	| `BusinessMsgNotice` | `BusinessNoticeManager` | 商家端消息通知 |
372	| `ShopAsset` | `ShopAsset` | 商城资源管理 |
373	| `WithdrawButton` | `WithdrawButtonManager` | 提现按钮管理 |
374	
375	### 4.3 PluginServiceProvider — 插件生命周期
376	
377	```
378	boot() 流程:
379	  1. 跳过安装路由 (request()->path() == 'install')
380	  2. 获取已启用插件列表 app('plugins')->getEnabledPlugins()
381	  3. 注册每个插件的翻译命名空间 + 视图命名空间
382	  4. 注册 SPL 自动加载 (Yunshop\ 命名空间前缀 → plugins/{name}/src/)
383	  5. 调用每个插件的 app()->init()
384	
385	registerPluginCallbackListener():
386	  监听 PluginWasEnabled / PluginWasDeleted / PluginWasDisabled 事件
387	  → $event->plugin->app()->toPublishes()
388	  → Artisan::call('vendor:publish', ['--tag' => $plugin->name])
389	  → 如果存在 callbacks.php，执行对应回调
390	```
391	
392	---
393	
394	## 5. 中间件链路
395	
396	### 5.1 中间件执行顺序
397	
398	每个请求经过的中间件由 `app/Kernel.php` 定义，分为三个层级：
399	
400	```
401	HTTP 请求
402	  │
403	  ├─ [全局中间件] (每个请求都执行)
404	  │    ├─ CheckForMaintenanceMode  — Laravel 原生，维护模式检测
405	  │    └─ ShopRoute               — 商城路由预处理
406	  │
407	  ├─ [路由组中间件] (根据路由前缀匹配)
408	  │    ├─ admin 组: Install → AddQueuedCookies → StartSession → ShareErrorsFromSession → SubstituteBindings → AuthenticateSession
409	  │    ├─ api 组:   throttle:60,1
410	  │    ├─ web 组:   (空)
411	  │    └─ business 组: AddQueuedCookies → StartSession → ShareErrorsFromSession → SubstituteBindings → AuthenticateSession
412	  │
413	  └─ [路由中间件] (路由定义中指定)
414	       ├─ auth / authAdmin / authShop / AuthenticateFrontend
415	       ├─ checkPasswordSafe
416	       ├─ shopBootStrap
417	       ├─ check
418	       ├─ business / businessLogin
419	       └─ rateLimiter
420	```
421	
422	### 5.2 认证中间件详解
423	
424	| 中间件 | 文件 | 认证方式 |
425	|--------|------|----------|
426	| `auth` | [Authenticate.php](file:///e:/project/abangmishop/app/common/middleware/Authenticate.php) | Laravel Web Auth Guard，未登录重定向到登录页 |
427	| `authAdmin` | [AuthenticateAdmin.php](file:///e:/project/abangmishop/app/common/middleware/AuthenticateAdmin.php) (4.5KB) | 后台管理 Session 认证，检查 `Auth::guard('admin')->check()` |
428	| `authShop` | [AuthenticateShop.php](file:///e:/project/abangmishop/app/common/middleware/AuthenticateShop.php) | 商城后台供应商权限检查 |
429	| `AuthenticateFrontend` | [AuthenticateFrontend.php](file:///e:/project/abangmishop/app/common/middleware/AuthenticateFrontend.php) (3.8KB) | 前端 API Token 认证，支持 yz_token / min_token 多种认证方式 |
430	
431	### 5.3 功能中间件
432	
433	| 中间件 | 文件 | 触发机制 | 用途 |
434	|--------|------|----------|------|
435	| `ShopRoute` | [ShopRoute.php](file:///e:/project/abangmishop/app/common/middleware/ShopRoute.php) | 全局 | 根据 Cookie `uniacid` 自动注入请求参数 `i` |
436	| `shopBootStrap` | [ShopBootstrap.php](file:///e:/project/abangmishop/app/common/middleware/ShopBootstrap.php) | 路由级 | 商城初始化引导，加载站点设置 |
437	| `CheckPasswordSafe` | [CheckPasswordSafe.php](file:///e:/project/abangmishop/app/common/middleware/CheckPasswordSafe.php) | 路由级 | 检查管理员密码安全等级 |
438	| `check` | [Check.php](file:///e:/project/abangmishop/app/common/middleware/Check.php) | 路由级 | 系统健康检查 |
439	| `rateLimiter` | [RateLimiter.php](file:///e:/project/abangmishop/app/common/middleware/RateLimiter.php) | 路由级 | 自定义接口限流 |
440	
441	---
442	
443	## 6. 路由体系
444	
445	### 6.1 路由分发策略
446	
447	`RouteServiceProvider::map()` 根据 `config('app.framework')` 的值选择不同的路由加载策略：
448	
449	```php
450	public function map() {
451	    if (config('app.framework') == 'platform') {
452	        $this->mapWebBootRoutes();   // /api/boot
453	        $this->mapPlatformRoutes();   // /admin/*
454	        $this->mapShopRoutes();       // /admin/shop
455	        $this->mapApiRoutes();        // 前端API
456	    } else {
457	        $this->mapWebRoutes();        // 微擎模式
458	    }
459	    $this->mapBusinessRoutes();       // /business/*
460	    $this->mapOutsideRoutes();        // /outside/*
461	}
462	```
463	
464	### 6.2 路由文件清单
465	
466	| 文件 | 大小 | 路径前缀 | 中间件组 | 命名空间 | 用途 |
467	|------|------|----------|----------|----------|------|
468	| `routes/admin.php` | 13.3KB | `/admin` | `admin` | `app\platform\controllers` | 平台管理登录、安装向导、系统设置 |
469	| `routes/shop.php` | 0.2KB | `/admin` | `admin` | `app\platform\controllers` | 商城后台管理 |
470	| `routes/business.php` | 36.4KB | `/business/{uniacid}` | (自定义) | `business` | 商家端全量路由（最大文件） |
471	| `routes/outside.php` | 1.9KB | `/outside/{uniacid}` | `web` | `app\outside` | 对外开放 API |
472	| `routes/api.php` | 0.1KB | `/` | `web` | `app` | 前端公共 API |
473	| `routes/web.php` | 0.4KB | `/` | `web` | `app` | Web 通用路由 |
474	| `routes/boot.php` | - | `/api` | `web` | `app` | 引导路由 |
475	| `routes/console.php` | 0.6KB | - | - | - | Artisan 命令路由 |
476	
477	### 6.3 URL 命名约定
478	
479	| 访问场景 | URL 模式 | 示例 |
480	|----------|----------|------|
481	| 平台管理后台 | `/admin/{controller}/{action}` | `/admin/system/upload/upload` |
482	| 商城后台 | `/admin/shop/{controller}/{action}` | `/admin/shop/goods/list` |
483	| 商家端 | `/business/{uniacid}/{controller}/{action}` | `/business/1/order/index` |
484	| 外部接口 | `/outside/{uniacid}/{controller}/{action}` | `/outside/1/goods/search` |
485	| 前端 API | `/app/{module}.{controller}.{action}` | `/app/goods.getGoodsInfo` |
486	| 微擎入口 | `/addons/yun_shop/api.php?i={uniacid}&route=...` | 微擎兼容模式 |
487	
488	---
489	
490	## 7. 分层架构详解
491	
492	项目采用严格的 **Controller → Service → Repository → Infrastructure** 四层架构。
493	
494	```
495	┌──────────────────────────────────────────────────────────┐
496	│  Controllers (控制器层)                                    │
497	│  - 接收 HTTP 请求，参数校验，响应封装                         │
498	│  - 三大控制器域: backend/ frontend/ platform/               │
499	├──────────────────────────────────────────────────────────┤
500	│  Services (服务层)                                         │
501	│  - 核心业务逻辑，无状态设计                                  │
502	│  - 跨端复用: app/common/services/ (92个)                    │
503	│  - 前端专属: app/frontend/modules/ (30个模块)               │
504	├──────────────────────────────────────────────────────────┤
505	│  Repositories (仓储接口层)                                  │
506	│  - 数据访问抽象接口定义                                      │
507	│  - 与具体数据源解耦                                         │
508	├──────────────────────────────────────────────────────────┤
509	│  Infrastructure (基础设施层)                                │
510	│  - 仓储接口的具体实现                                       │
511	│  - 数据源适配: DB / API / Cache / File                      │
512	├──────────────────────────────────────────────────────────┤
513	│  Models / Entities (数据模型层)                             │
514	│  - Eloquent ORM 模型定义                                    │
515	│  - 属性访问器、关联关系、作用域                               │
516	└──────────────────────────────────────────────────────────┘
517	```
518	
519	### 7.1 Controllers 层
520	
521	**三大控制器域**：
522	
523	| 域 | 目录 | 监听模块 | 典型业务 |
524	|----|------|----------|----------|
525	| `backend` | [app/backend/](file:///e:/project/abangmishop/app/backend) | 后台管理 | 系统设置、权限管理、数据报表 |
526	| `frontend` | [app/frontend/](file:///e:/project/abangmishop/app/frontend) | 商城前端 API | 商品浏览、下单、支付、会员 |
527	| `platform` | [app/platform/](file:///e:/project/abangmishop/app/platform) | 平台管理 | 登录注册、安装向导、平台配置 |
528	| `outside` | [app/outside/](file:///e:/project/abangmishop/app/outside) | 外部接口 | 开放 API、第三方对接 |
529	| `business` | [business/](file:///e:/project/abangmishop/business) | 商家端 | 商家独立管理后台 |
530	
531	### 7.2 Services 层核心示例
532	
533	以 Project 模块为例，展示完整分层：
534	
535	**GoodsBaseService** (1820 行) — [app/frontend/modules/project/services/GoodsBaseService.php](file:///e:/project/abangmishop/app/frontend/modules/project/services/GoodsBaseService.php)
536	
537	```php
538	// 核心方法签名展示
539	class GoodsBaseService
540	{
541	    public function getGoodsData($goodsId);        // 完整商品数据加载
542	    public function getThreeModel($goodsId);        // 3D模型数据获取
543	    public function buildSpecTree($goodsId);        // 规格树构建与关联选项合并
544	    public function getBrandInfo($brandId);          // 品牌信息
545	    public function getRecommendGoods($goodsId);     // 推荐商品
546	}
547	```
548	
549	缓存策略：商品详情缓存 24 小时，3D 模型数据实时加载。内置详细的执行时间日志记录。
550	
551	**GoodsSearchService** (1142 行) — [app/frontend/modules/project/services/GoodsSearchService.php](file:///e:/project/abangmishop/app/frontend/modules/project/services/GoodsSearchService.php)
552	
553	```php
554	class GoodsSearchService
555	{
556	    public function search($keyword, $filters, $sort, $page);  // MeiliSearch 全文搜索
557	    public function getFilterOptions($keyword, $currentFilters); // 多维度联动筛选
558	    public function getSearchSuggestions($keyword);     // 搜索建议
559	    public function getSearchHistory($memberId);        // 搜索历史
560	}
561	```
562	
563	### 7.3 Repositories 层与 Infrastructure 层
564	
565	**GoodsRepository** (1465 行) — [app/frontend/modules/project/infrastructure/GoodsRepository.php](file:///e:/project/abangmishop/app/frontend/modules/project/infrastructure/GoodsRepository.php)
566	
567	```php
568	class GoodsRepository implements GoodsRepositoryInterface
569	{
570	    public function getGoodsById($id);           // 商品基础查询
571	    public function analysis($dwgFile);          // CAD DWG 文件解析
572	    public function analysisV2($files);          // CAD 多文件批量解析
573	    public function batchUpdateGoods($data);     // 商品批量操作
574	    public function getGoodsByCategory($catId);  // 按分类查询
575	}
576	```
577	
578	### 7.4 Models 层
579	
580	**BaseModel** — 自定义基础模型位于 [app/framework/Model/](file:///e:/project/abangmishop/app/framework/Model/)
581	
582	特性和约定：
583	- 所有数据库查询结果默认 `FETCH_ASSOC`（通过 `AppServiceProvider` 全局设置）
584	- `ModelExpansionManager` 支持运行时动态扩展 Model 行为
585	- 公共 Model 位于 [app/common/models/](file:///e:/project/abangmishop/app/common/models/)（146 个文件）
586	- 使用 `ims_` 表前缀（微擎兼容）
587	
588	### 7.5 前端业务模块
589	
590	[app/frontend/modules/](file:///e:/project/abangmishop/app/frontend/modules/) 包含 30 个前端业务模块：
591	
592	| 模块 | 职责 | 子模块数 |
593	|------|------|----------|
594	| `cart/` | 购物车管理 | 9 |
595	| `coupon/` | 优惠券 | 4 |
596	| `deduction/` | 抵扣体系 | 16 |
597	| `dispatch/` | 配送物流 | 6 |
598	| `finance/` | 财务管理 | 9 |
599	| `goods/` | 商品管理 | 6 |
600	| `member/` | 会员中心 | 5 |
601	| `order/` | 订单管理 | 24 |
602	| `orderGoods/` | 订单商品 | 14 |
603	| `orderPay/` | 订单支付 | 2 |
604	| `payment/` | 支付管理 | 2 |
605	| `project/` | 3D 可视化 | 6 |
606	| `refund/` | 退款售后 | 3 |
607	| `withdraw/` | 提现管理 | 4 |
608	| 其他 | accessToken, coin, home, income 等 | - |
609	
610	---
611	
612	## 8. 插件架构
613	
614	### 8.1 插件目录规范
615	
616	每个插件遵循统一的目录结构：
617	
618	```
619	plugins/{plugin-name}/
620	├── src/              # 插件核心 PHP 逻辑 (Yunshop\{PluginName}\ 命名空间)
621	├── views/            # 插件视图模板 (Blade)
622	├── assets/           # 插件静态资源 (JS/CSS/图片)
623	├── config/           # 插件配置文件
624	├── lang/             # 多语言翻译文件
625	├── migrations/       # 插件专属数据库迁移
626	├── plugin.json       # 插件清单文件 (名称/版本/依赖/配置项)
627	└── callbacks.php     # 插件生命周期回调 (可选)
628	```
629	
630	### 8.2 插件自动加载机制
631	
632	`PluginServiceProvider` 使用 SPL `spl_autoload_register` 实现插件的自动类加载：
633	
634	```
635	类名 → 命名空间解析逻辑:
636	  Yunshop\Supplier\services\GoodsService
637	    ↓ 匹配命名空间前缀
638	    ↓ 映射到 plugins/supplier/src/
639	    ↓ 拼接路径
640	  plugins/supplier/src/services/GoodsService.php
641	```
642	
643	关键代码逻辑：
644	```php
645	spl_autoload_register(function ($class) use ($paths) {
646	    if (!(mb_strpos($class, 'Yunshop') === 0)) {
647	        return false; // 非插件类，跳过
648	    }
649	    foreach (array_keys($paths) as $namespace) {
650	        if (mb_strpos($class, $namespace) === 0) {
651	            $path = $paths[$namespace] . Str::replaceFirst($namespace, '', $class) . ".php";
652	            if (file_exists($path)) {
653	                include $path;
654	                return true;
655	            }
656	        }
657	    }
658	    return false;
659	});
660	```
661	
662	### 8.3 插件生命周期
663	
664	```mermaid
665	graph LR
666	    ENABLE[启用插件] --> EVENT_ENABLED[PluginWasEnabled 事件]
667	    DISABLE[禁用插件] --> EVENT_DISABLED[PluginWasDisabled 事件]
668	    DELETE[删除插件] --> EVENT_DELETED[PluginWasDeleted 事件]
669	
670	    EVENT_ENABLED --> PUBLISH[Artisan::call vendor:publish]
671	    EVENT_DISABLED --> PUBLISH
672	    EVENT_DELETED --> PUBLISH
673	
674	    PUBLISH --> CALLBACK{callbacks.php 存在?}
675	    CALLBACK -->|是| EXEC_CB[执行对应回调函数]
676	    CALLBACK -->|否| DONE[完成]
677	    EXEC_CB --> DONE
678	```
679	
680	每当插件的启用/禁用/删除状态变更时，系统会：
681	1. 触发对应的事件 (`PluginWasEnabled/Disabled/Deleted`)
682	2. 调用 `vendor:publish` 发布插件的静态资源
683	3. 检查并执行 `callbacks.php` 中定义的生命周期回调
684	
685	### 8.4 插件分类
686	
687	| 分类 | 数量 | 代表插件 |
688	|------|------|----------|
689	| **营销插件** | 15+ | commission(分销), group-code(拼团), lucky-draw(抽奖), share-activity(分享), coupon-qr(优惠券), random-discount(随机折扣) |
690	| **会员插件** | 8+ | member-price(会员价), member-tags(标签), new-member-prize(新人礼), real-name-auth(实名认证), sign(签到) |
691	| **商品插件** | 6+ | goods-assistant(商品助手), goods-ranking(排行榜), supplier(供应商), point-mall(积分商城), sweep-buy(扫购) |
692	| **内容插件** | 5+ | article(文章), broadcast(直播), picture-album(相册), material-center(素材中心) |
693	| **企业微信** | 6+ | work-wechat(企微), wechat-chat-sidebar(聊天侧边栏), customer-increase(客户增长), sop-task(SOP任务) |
694	| **运营工具** | 10+ | shop-statistics(统计), customer-manage(客户管理), customer-radar(客户雷达), shop-esign(电子签章), large-screen(大屏) |
695	| **交易支付** | 5+ | pay-manage(支付管理), service-fee(服务费), invoice(发票), electronics-bill(电子面单) |
696	| **配送物流** | 5+ | city-delivery(同城配送), exhelper(快递助手), package-delivery(包裹配送), express-company(快递公司) |
697	| **门店POS** | 4+ | shop-pos(门店POS), shop-assistant(店长助手), shop-clerk(店员管理), storeaggregate(门店聚合) |
698	| **小程序/App** | 4+ | min-app(小程序), pc-terminal(PC端), appletslive(小程序直播), pc-terminal-two(PC端V2) |
699	| **第三方对接** | 5+ | meituan-group-buy(美团), tiktok-group-buy(抖音), jd-supply(京东供应链), leshua-pay(乐刷支付) |
700	
701	### 8.5 插件开发最佳实践
702	
703	1. **命名空间**: 使用 `Yunshop\{PluginName}\` 命名空间，与 SPL 自动加载一致
704	2. **插件入口**: 在 `src/` 下定义插件 App 类，实现 `init()` 方法
705	3. **路由注册**: 在 `init()` 中注册插件专属路由
706	4. **配置项**: 在 `plugin.json` 中声明插件配置，通过 `Setting::get()` 读取
707	5. **事件监听**: 使用 EventServiceProvider 的 `$subscribe` 注册插件级监听器
708	6. **状态检测**: 使用 `app('plugins')->isEnabled('plugin-name')` 判断插件是否启用
709	
710	---
711	
712	## 9. 支付架构
713	
714	### 9.1 支付系统整体架构
715	
716	```mermaid
717	graph TB
718	    ORDER[订单创建] --> PM[PaymentManager 支付管理器]
719	    PM --> REGISTER[PaymentServiceProvider 注册支付通道]
720	    REGISTER --> CHANNELS[50+ 支付通道]
721	
722	    subgraph 支付通道分类
723	        WX[微信支付: wechat/wechatscan/wxIntegrationPay]
724	        ALI[支付宝: alipay/zfbIntegrationPay]
725	        UNION[银联/云闪付: eup/yunpay/yoppay/yoppro]
726	        CONV[聚合支付: convergepay/convergequickpay/convergeseparate]
727	        CROSS[跨境支付: paypal/usdtpay]
728	        BALANCE[余额支付: storebalance/membercard/silverPointPay]
729	        CREDIT[分期/信用: merchantLoanPay/huibeiPay]
730	        THIRD[三方通道: sandpay/lakala/leshua/huanxun]
731	    end
732	
733	    CHANNELS --> PAY_CALL[发起支付请求]
734	    PAY_CALL --> CALLBACK[payment/{channel}/notifyUrl.php]
735	    CALLBACK --> VERIFY[签名验证]
736	    VERIFY --> UPDATE[更新订单状态 → 触发支付完成事件]
737	```
738	
739	### 9.2 支付通道目录结构
740	
741	`payment/` 目录下每个通道独立为一个子目录：
742	
743	```
744	payment/{channel}/
745	├── notifyUrl.php          # 支付异步回调入口
746	├── refundNotifyUrl.php    # 退款异步回调入口 (可选)
747	├── lib/                   # 通道 SDK 封装
748	└── config.php             # 通道配置 (可选)
749	```
750	
751	### 9.3 支付回调流程
752	
753	```
754	第三方支付平台发送异步通知
755	  → payment/{channel}/notifyUrl.php 接收
756	  → 签名验证（RSA/MD5/SM2 等，因通道而异）
757	  → 验签通过 → 解析回调数据
758	  → 查找对应订单 (通过 out_trade_no / transaction_id)
759	  → 更新订单支付状态 (ims_yz_order 表)
760	  → 触发支付完成事件 (AfterOrderPaidEvent)
761	  → 返回成功响应 (SUCCESS / success 标识)
762	```
763	
764	### 9.4 支付安全机制
765	
766	| 机制 | 实现 |
767	|------|------|
768	| **CSRF 白名单** | 所有支付回调 URL 排除在 CSRF 保护之外（文件级独立入口） |
769	| **签名验证** | 各通道按各自规范进行签名校验（RSA、MD5、HMAC-SHA256 等） |
770	| **幂等处理** | 通过 `out_trade_no` 唯一约束防止重复回调 |
771	| **日志记录** | `PayLog` 事件 + `PayLogListener` 保存完整的支付请求与响应参数 |
772	| **金额校验** | 回调金额必须与订单金额一致 |
773	
774	### 9.5 支付通道一览
775	
776	| 分类 | 支付通道 (50+) |
777	|------|---------------|
778	| **微信支付** | wechat, wechatscan, wxIntegrationPay, wxIntegrationSharePay, thirdPartyWechat, hfMiniIntegrationPay, hfkjIntegrationPay |
779	| **支付宝** | alipay, zfbIntegrationPay, zfbIntegrationSharePay, alipayPeriodDeduct |
780	| **银联/云闪付** | eup, yunpay, yoppay, yoppro, yopsystem, yopmerchant |
781	| **聚合支付** | convergepay, convergequickpay, convergeseparate, storeaggregate, lklIntegrationPay, lklIntegrationSharePay |
782	| **跨境支付** | paypal, usdtpay |
783	| **余额/储值** | storebalance, membercard, silverPointPay, rechargeplatform |
784	| **分期/信用** | merchantLoanPay, huibeiPay, dragondeposit |
785	| **三方通道** | sandpay(杉德), lakala(拉卡拉), leshua(乐刷), huanxun(环迅), toutiaopay(头条), jueqi(崛起), jinepay(金e), xfpay(先锋), dianbangscan(点帮) |
786	| **其他** | icbcPay(工行), hkscan(汇康), pld(普兰丁), wft(威富通), authPay(授权支付), cashierqrcode, cloud, taxWithdraw, consolWithdraw, workerWithdraw |
787	
788	---
789	
790	## 10. 事件驱动架构
791	
792	### 10.1 事件系统设计
793	
794	项目大量使用 Laravel 事件系统实现业务解耦，所有核心业务变更必须通过事件触发，不允许在业务代码中直接调用副作用逻辑。
795	
796	```
797	事件触发: event(new SomeEvent($data))
798	  ↓
799	EventServiceProvider 中的 $listen 映射
800	  ↓
801	对应 Listener 的 handle() 方法执行
802	  ↓
803	(可选) 在 Listener 中 dispatch Job 到异步队列
804	```
805	
806	### 10.2 EventServiceProvider 核心映射
807	
808	**文件**: [app/common/providers/EventServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/EventServiceProvider.php)
809	
810	| 事件 | 监听器 | 触发时机 | 业务含义 |
811	|------|--------|----------|----------|
812	| `OrderDispatchWasCalculated` | `UnifyOrderDispatchPrice` + `TemplateOrderDispatchPrice` | 下单时 | 计算统一运费 + 模板运费 |
813	| `AfterOrderCreatedEvent` | `AfterOrderCreatedListener` + `OrderCreateCertified` | 下单成功后 | 会员关系绑定 + 实名认证关联 |
814	| `AfterOrderCreatedImmediatelyEvent` | `Order` (清空购物车 Listener) | 下单完成 | 清空购物车已购商品 |
815	| `PayLog` | `PayLogListener` | 支付请求时 | 保存支付请求参数到日志 |
816	| `BecomeAgent` | `BecomeAgentListener` | 会员成为下级 | 建立分销关系 |
817	| `MemberCreateRelationEvent` | `MemberCreateRelationEventListener` | 会员关系创建 | 处理分销关系链 |
818	| `MemberChangeRelationEvent` | `MemberChangeRelationEventListener` | 会员关系变更 | 更新关系链 |
819	| `AfterOrderPayTypeChangedEvent` | `AfterOrderPayTypeChangedListener` | 支付方式变更 | 处理汇款支付场景 |
820	| `AfterMemberReceivedCoupon` | `AfterMemberReceivedCouponListener` | 领取优惠券 | 优惠券发放后处理 |
821	| `WechatProcessor` | `WechatProcessorListener` | 微信消息推送 | 处理微信消息事件 |
822	| `WechatMessage` | `WechatMessageListener` + `WechatMinPayNotifyListener` | 微信模板消息 | 消息通知 + 小程序支付管理通知 |
823	| `UserActionEvent` | `UserActionListener` | 用户行为 | 记录用户操作日志 |
824	| `AfterProcessStateChangedEvent` | `StateContainer` | 流程状态变更 | 状态流转处理 |
825	| `GoodsOptionChanged` | `UpdateSearchIndex` | 商品规格变更 | 更新 MeiliSearch 搜索索引 |
826	
827	### 10.3 Subscriber 订阅者模式
828	
829	通过 `$subscribe` 数组注册需要多事件监听的复杂监听器：
830	
831	```php
832	protected $subscribe = [
833	    orderListener::class,              // 订单全生命周期 (创建/支付/发货/收货/完成)
834	    GoodsStock::class,                 // 商品库存 (预扣/释放/回滚)
835	    BalanceRechargeCompletedListener::class, // 余额充值完成
836	    WithdrawApplyListener::class,      // 提现申请
837	    WithdrawAuditListener::class,      // 提现审核
838	    WithdrawPayListener::class,        // 提现打款
839	    WithdrawSuccessListener::class,    // 提现成功
840	    LevelListener::class,              // 会员等级升级
841	    BalanceListener::class,            // 余额变动
842	    PointListener::class,              // 积分变动
843	    CouponDiscount::class,             // 下单赠送优惠券
844	    OrderClosedListener::class,        // 订单关闭返还优惠券
845	    GoodsChangeListener::class,        // 商品上下架/库存变更 → 系统消息通知
846	    PointsRewardListener::class,       // 余额充值赠送积分
847	    // ... 共 20+ Subscriber
848	];
849	```
850	
851	### 10.4 事件目录分类
852	
853	[app/common/events/](file:///e:/project/abangmishop/app/common/events/) 下按业务域分类：
854	
855	| 子目录 | 事件数量 | 业务域 |
856	|--------|----------|--------|
857	| `order/` | 73 | 订单全生命周期事件 |
858	| `member/` | 36 | 会员/分销/关系链事件 |
859	| `withdraw/` | 17 | 提现审核/打款事件 |
860	| `plugin/` | 15 | 插件生命周期事件 |
861	| `finance/` | 9 | 财务/余额事件 |
862	| `goods/` | 8 | 商品上下架/库存事件 |
863	| `cart/` | 7 | 购物车操作事件 |
864	| `balance/` | 3 | 余额变动事件 |
865	| 其他 | 10+ | coupon, dispatch, category, payment, home, tag, income, systemMsg |
866	
867	---
868	
869	## 11. 异步队列架构
870	
871	### 11.1 队列基础设施
872	
873	- **驱动**: Redis Queue
874	- **连接**: `default` Redis 连接 (db 0)
875	- **队列名**: `default` (主队列)
876	- **常驻进程**: Workerman 4.1.15 (MQTT 长连接)
877	- **任务重试**: `--tries=3`
878	- **超时时间**: `--timeout=600` (10 分钟)
879	
880	### 11.2 67 个 Job 类分类
881	
882	| 类别 | 数量 | 关键 Job | 用途 |
883	|------|------|----------|------|
884	| **订单处理** | 8 | `OrderCreatedEventQueueJob`, `OrderPaidEventQueueJob`, `OrderReceivedEventQueueJob`, `OrderSentEventQueueJob`, `OrderBonusJob` | 订单事件异步分发与分红计算 |
885	| **会员关系** | 6 | `ChangeMemberRelationJob`, `ModifyRelationJob`, `ModifyRelationshipChainJob`(14.5KB), `MemberLowerOrderJob`(11.6KB), `MemberLowerGroupOrderJob` | 关系链重建/修改 + 下级订单统计 |
886	| **商品处理** | 5 | `GoodsImageJob`, `GoodsSetPriceJob`, `BatchImportGoodsJob`, `AssemblyGoodsJob` | 商品图片处理/调价/导入/组装 |
887	| **3D/文件** | 4 | `UploadModelJob`(13.4KB), `ProductCadJob`(6.1KB), `GeneratePdfJob`, `HighPptJob` | 3D模型上传/CAD解析/PDF生成/PPT转换 |
888	| **消息通知** | 10 | `MessageJob`, `MiniMessageNoticeJob`(6KB), `MessageNoticeJob`, `MqttTopicMessageJob` | 通用消息/小程序模板消息/系统通知/MQTT推送 |
889	| **搜索同步** | 2 | `UpdateMeiliSearch` | 商品搜索索引更新 |
890	| **财务结算** | 5 | `PeriodMergeJob`, `OrderMergeJob`, `OrderMergeCreateJob` | 周期结算/订单合并 |
891	| **其他** | 27 | 支付、提现、物流、数据导出等 | 各类业务异步任务 |
892	
893	### 11.3 Supervisor 守护配置
894	
895	```ini
896	[program:yunshop-queue]
897	process_name=%(program_name)s_%(process_num)02d
898	command=php /path/to/abangmishop/artisan queue:work redis --queue=default --tries=3 --timeout=600
899	directory=/path/to/abangmishop
900	autostart=true
901	autorestart=true
902	numprocs=4
903	redirect_stderr=true
904	stdout_logfile=/var/log/supervisor/yunshop-queue.log
905	
906	[program:yunshop-workerman]
907	process_name=%(program_name)s
908	command=php /path/to/abangmishop/artisan shop start
909	directory=/path/to/abangmishop
910	autostart=true
911	autorestart=true
912	stdout_logfile=/var/log/supervisor/yunshop-workerman.log
913	```
914	
915	### 11.4 队列运维命令
916	
917	```bash
918	# 查看失败任务
919	php artisan queue:failed
920	
921	# 重试所有失败任务
922	php artisan queue:retry all
923	
924	# 清空失败任务
925	php artisan queue:flush
926	
927	# 使用守护脚本启动
928	bash daemon.sh /usr/bin/php
929	```
930	
931	---
932	
933	## 12. 数据架构
934	
935	### 12.1 MySQL
936	
937	| 连接名 | 数据库 | 用途 | 配置来源 |
938	|--------|--------|------|----------|
939	| `mysql` | `abangmi_com` | 核心业务数据（主库） | `.env` → `config/database.php` |
940	| `mysql_slave` | (未启用) | 读写分离（从库） | `database/config.php` |
941	| `kefu` | `dm299_com` | 客服系统独立数据库 | `.env` |
942	
943	**配置加载优先级**:
944	```
945	1. .env 环境变量 (当 APP_Framework=platform)
946	2. database/config.php (当 APP_Framework != 'platform')
947	3. config/database.php (env() 辅助函数兜底默认值)
948	```
949	
950	**表前缀**: `ims_`（微擎兼容约定）
951	
952	### 12.2 MongoDB
953	
954	通过 `jenssegers/mongodb` 3.8.6 集成，用于文档型数据存储（如商品规格的复杂嵌套结构、用户行为日志等）。
955	
956	| 配置项 | 值 |
957	|--------|-----|
958	| 主机 | `127.0.0.1` |
959	| 端口 | `27017` |
960	| 数据库 | 与 MySQL 主库同名 |
961	
962	### 12.3 Redis
963	
964	双连接架构：
965	
966	| 连接 | 数据库 | 用途 |
967	|------|--------|------|
968	| `default` | db 0 | Session 存储、队列、限流计数、临时缓存 |
969	| `cache` | db 1 | 业务数据持久缓存（商品详情、配置项等） |
970	
971	**核心 Key 命名规范与 TTL**:
972	
973	| 用途 | Key 模式 | TTL |
974	|------|----------|-----|
975	| 下载分钟限流 | `dl_limit:{file_type}:{goods_id}:{ip}:minute` | 60s |
976	| 下载小时配额 | `dl_limit:{file_type}:{goods_id}:{ip}:hour` | 3600s |
977	| IP 黑名单 | `dl_blacklist:{ip}` | 可配置 |
978	| 商品详情缓存 | `goods:detail:{goods_id}` | 86400s (24h) |
979	| 3D 模型数据 | `goods:three_model:{goods_id}` | 实时加载 |
980	| PHP Session | `PHPSESSID:{session_id}` | 可配置 |
981	
982	### 12.4 搜索引擎
983	
984	支持双引擎切换：
985	
986	| 引擎 | 驱动 | 集成方式 | 适用场景 |
987	|------|------|----------|----------|
988	| **MeiliSearch** | `meilisearch` | `meilisearch/meilisearch-php` 1.16+ + Laravel Scout | 轻量级，内置中文分词，推荐 |
989	| **Elasticsearch** | `elasticsearch` | `matchish/laravel-scout-elasticsearch` + Laravel Scout | 重量级，适合超大规模 |
990	
991	通过 `.env` 配置切换：
992	```env
993	SCOUT_DRIVER=meilisearch
994	MEILISEARCH_HOST=http://127.0.0.1:7700
995	MEILISEARCH_KEY=
996	SEARCH_URL=http://127.0.0.1:8009
997	```
998	
999	---
1000	
1001	
1002	
1003	## 14. 前端架构概述
1004	
1005	### 14.1 构建工具
1006	
1007	- **Laravel Mix** (Webpack 6.0.49): 前端资源编译打包
1008	- **入口文件**: `webpack.mix.js`
1009	- **编译命令**:
1010	  ```bash
1011	  npm run dev   # 开发环境
1012	  npm run prod  # 生产环境
1013	  ```
1014	
1015	### 14.2 3D 可视化技术栈
1016	
1017	| 技术 | 版本 | 用途 |
1018	|------|------|------|
1019	| **Three.js** | 0.171.0 | WebGL 3D 渲染引擎，支持多种 3D 模型格式在线预览 |
1020	| **Tween.js** | 18.6.4 | 补间动画库，模型旋转/缩放/视角切换动画 |
1021	
1022	### 14.3 微擎前端约定
1023	
1024	前端静态资源部署在 `addons/yun_shop/static/`，遵循微擎模块标准：
1025	
1026	```
1027	addons/yun_shop/static/
1028	├── js/          # JavaScript 文件
1029	├── css/         # 样式表
1030	├── images/      # 图片资源
1031	└── shopConfig.js  # ★ 商城前端核心配置（含高德地图 Key 等）
1032	```
1033	
1034	### 14.4 静态资源目录
1035	
1036	`static/` 目录下按业务分类：
1037	```
1038	static/
1039	├── js/
1040	├── css/
1041	├── images/
1042	├── fonts/
1043	└── ... (17 个子目录)
1044	```
1045	
1046	---
1047	
1048	## 15. 安全架构
1049	
1050	### 15.1 认证体系
1051	
1052	```mermaid
1053	graph LR
1054	    REQ[请求] --> SWITCH{路径判断}
1055	
1056	    SWITCH -->|/admin/*| ADMIN[authAdmin<br/>Session 认证]
1057	    SWITCH -->|/business/*| BIZ[businessLogin<br/>商家 Session 认证]
1058	    SWITCH -->|前端 API| FRONT[authShop + AuthenticateFrontend<br/>Token/YzToken 认证]
1059	    SWITCH -->|支付回调| PAY[无认证<br/>签名验证代替]
1060	
1061	    ADMIN --> CHECK_OK{验证通过?}
1062	    CHECK_OK -->|是| CONTROLLER[控制器]
1063	    CHECK_OK -->|否| REDIRECT[重定向登录页]
1064	
1065	    FRONT --> TOKEN_OK{Token 有效?}
1066	    TOKEN_OK -->|是| CONTROLLER
1067	    TOKEN_OK -->|否| JSON_ERR[返回 401 JSON]
1068	```
1069	
1070	### 15.2 前端 API 多 Token 认证
1071	
1072	`YunShop::app()->getMemberId()` 支持从多种来源解析用户身份：
1073	
1074	| Token 类型 | type 值 | 来源 |
1075	|------------|---------|------|
1076	| 原生 App | 9 | Header: `yz_token` → Redis Session |
1077	| 主播 App | 14 | Header: `yz_token` → 主播认证 |
1078	| CPS 聚合 App | 15 | Header: `yz_token` → CPS 认证 + AppID 校验 |
1079	| 小程序 | - | URL: `min_token` → Redis `PHPSESSID:{min_token}` |
1080	| 门店 POS | - | `shop-pos` 插件 → POS 用户 |
1081	| Web Session | - | `Session::get('member_id')` |
1082	
1083	### 15.3 API 限流
1084	
1085	| 层级 | 实现 | 配置 |
1086	|------|------|------|
1087	| **全局限流** | `throttle:60,1` (api 路由组) | 每分钟 60 次请求 |
1088	| **路由级限流** | `rateLimiter` 中间件 | 可针对特定路由自定义 |
1089	| **下载限流** | `DownloadLimitService` | IP+GoodsID+FileType 三级限流 |
1090	
1091	### 15.4 下载限流详解
1092	
1093	**文件**: [app/frontend/modules/project/services/DownloadLimitService.php](file:///e:/project/abangmishop/app/frontend/modules/project/services/DownloadLimitService.php)
1094	
1095	三层防护策略：
1096	
1097	| 层级 | 维度 | 实现 | TTL |
1098	|------|------|------|-----|
1099	| Layer 1 | IP + GoodsID + FileType | 分钟级 Redis 计数器 | 60s |
1100	| Layer 2 | IP + GoodsID + FileType | 小时级 Redis 配额 | 3600s |
1101	| Layer 3 | IP | 全局黑名单 | 可配置 |
1102	
1103	| 文件类型 | 分钟限流 | 小时配额 |
1104	|----------|----------|----------|
1105	| `3d` (3D模型) | 10次/分钟 | 100次/小时 |
1106	| `cad` (CAD文件) | 15次/分钟 | 150次/小时 |
1107	| `atlas` (图册PDF) | 20次/分钟 | 300次/小时 |
1108	| `color_card` (色卡) | 20次/分钟 | 300次/小时 |
1109	
1110	### 15.5 CSRF 保护与支付白名单
1111	
1112	- 全局 CSRF 中间件在 web 路由组中**被注释**，但管理后台组启用
1113	- 所有支付回调 URL 通过独立入口文件 (`payment/{channel}/notifyUrl.php`) 跳出 Laravel 中间件链，天然免除 CSRF
1114	- `app/Kernel.php` 中 `EncryptCookies` 被注释，Cookie 不自动加密
1115	
1116	### 15.6 密码安全
1117	
1118	- `CheckPasswordSafe` 中间件检查管理员密码复杂度
1119	- `APP_KEY` 使用 AES-256-CBC 算法
1120	- 管理员密码变更需通过 `ResetpwdController@authPassword` 验证原密码
1121	
1122	---
1123	
1124	## 16. 扩展点与定制化
1125	
1126	### 16.1 模型扩展 — ModelExpansionManager
1127	
1128	`ShopProvider` 中注册的 `ModelExpansionManager` 允许在运行时动态向现有 Model 添加方法、作用域、关联关系，无需修改原始 Model 文件。
1129	
1130	```php
1131	// 使用示例 (概念)
1132	app('ModelExpansionManager')->addMethod(
1133	    BaseModel::class,
1134	    'getCustomAttribute',
1135	    function () { return $this->custom_calc(); }
1136	);
1137	```
1138	
1139	### 16.2 Blade 事件过滤器
1140	
1141	项目自定义了两个 Blade 指令，支持在视图渲染时进行内容过滤：
1142	
1143	```php
1144	// AppServiceProvider 注册
1145	Blade::directive('filterBlade', function ($expression) {
1146	    return "<?php ob_start() ?>";
1147	});
1148	Blade::directive('endFilterBlade', function ($expression) {
1149	    return '<?php $output = \TorMorten\Eventy\Facades\Eventy::filter('.$expression.', ob_get_clean());
1150	            echo $output; ?>';
1151	});
1152	```
1153	
1154	视图使用：
1155	```blade
1156	@filterBlade
1157	    <!-- 原始内容 -->
1158	@endFilterBlade('my.filter.hook')
1159	```
1160	
1161	### 16.3 Eventy 事件过滤系统
1162	
1163	通过 `tormjens/eventy` 包实现 WordPress 风格的 Filter/Action 钩子系统，插件可以通过以下方式修改核心行为：
1164	
1165	```php
1166	// 注册过滤器
1167	Eventy::addFilter('goods.detail.extra', function($data) {
1168	    $data['custom_field'] = 'value';
1169	    return $data;
1170	});
1171	
1172	// 注册动作
1173	Eventy::addAction('order.after_create', function($order) {
1174	    // 自定义逻辑
1175	});
1176	```
1177	
1178	### 16.4 Manager 模式扩展
1179	
1180	核心业务 Manager 全部以 singleton 注册在 IoC 容器中，支持扩展：
1181	
1182	| Manager | Key | 扩展方式 |
1183	|---------|-----|----------|
1184	| `GoodsManager` | `GoodsManager` | 通过 `app()->extend()` 替换或装饰 |
1185	| `OrderManager` | `OrderManager` | 注册新的订单类型处理器 |
1186	| `PaymentManager` | `PaymentManager` (动态) | 新增支付通道注册 |
1187	| `DeductionManager` | `DeductionManager` | 注册新的抵扣类型 |
1188	| `CoinManager` | `CoinManager` | 注册新的虚拟币类型 |
1189	| `GoodsWidgetContainer` | `GoodsWidgetContainer` | 注册商品管理挂件 |
1190	| `CartContainer` | `CartContainer` | 扩展购物车行为 |
1191	| `MemberCenter` | `MemberCenter` | 注册会员中心功能入口 |
1192	| `WithdrawButton` | `WithdrawButton` | 注册提现方式按钮 |
1193	
1194	### 16.5 自定义 Artisan Command
1195	
1196	Artisan 命令位于 [app/console/](file:///e:/project/abangmishop/app/console/)，包括：
1197	
1198	| 命令 | 用途 |
1199	|------|------|
1200	| `shop start` | 启动 Workerman MQTT 服务 |
1201	| `shop stop` | 停止 Workerman |
1202	| `shop restart` | 重启 Workerman |
1203	| `shop status` | 查看 Workerman 状态 |
1204	| `cron:run` | 执行计划任务 |
1205	| `meilisearch:reindex` | 重建搜索索引 |
1206	
1207	### 16.6 插件级扩展
1208	
1209	- 插件通过 `init()` 方法注册自身路由、事件监听器
1210	- 插件视图可通过 `@namespace::view.name` 被主应用引用
1211	- 插件翻译文件通过 `trans('namespace::file.key')` 调用
1212	- 插件间通过事件系统松耦合通信
1213	
1214	---
1215	
1216	## 附录 A: 关键文件快速索引
1217	
1218	| 用途 | 文件路径 |
1219	|------|----------|
1220	| 应用入口 | [index.php](file:///e:/project/abangmishop/index.php) |
1221	| Laravel 引导 | [app/laravel.php](file:///e:/project/abangmishop/app/laravel.php) |
1222	| 应用创建 | [bootstrap/app.php](file:///e:/project/abangmishop/bootstrap/app.php) |
1223	| 芸众核心 | [app/yunshop.php](file:///e:/project/abangmishop/app/yunshop.php) |
1224	| HTTP 内核 | [app/Kernel.php](file:///e:/project/abangmishop/app/Kernel.php) |
1225	| 自定义 Application | [app/framework/Foundation/Application.php](file:///e:/project/abangmishop/app/framework/Foundation/Application.php) |
1226	| 事件注册 | [app/common/providers/EventServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/EventServiceProvider.php) |
1227	| 路由注册 | [app/common/providers/RouteServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/RouteServiceProvider.php) |
1228	| 插件注册 | [app/common/providers/PluginServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/PluginServiceProvider.php) |
1229	| 核心 Manager 注册 | [app/common/providers/ShopProvider.php](file:///e:/project/abangmishop/app/common/providers/ShopProvider.php) |
1230	| 微擎兼容层 | [app/common/providers/YunShopServiceProvider.php](file:///e:/project/abangmishop/app/common/providers/YunShopServiceProvider.php) |
1231	| 中间件注册 | [app/common/middleware/](file:///e:/project/abangmishop/app/common/middleware/) |
1232	| 全局辅助函数 | [app/helpers.php](file:///e:/project/abangmishop/app/helpers.php) (141KB) |
1233	| 环境配置 | [.env](file:///e:/project/abangmishop/.env) |
1234	| 数据库配置 | [config/database.php](file:///e:/project/abangmishop/config/database.php) / [database/config.php](file:///e:/project/abangmishop/database/config.php) |
1235	
1236	## 附录 B: 与现有文档的关系
1237	
1238	本架构设计文档是项目文档体系的核心组成，与以下文档互为补充：
1239	
1240	| 文档 | 侧重 |
1241	|------|------|
1242	| `docs/repo_wiki.md` | 仓库 Wiki — 快速了解项目概况、模块列表、基础开发指南 |
1243	| `docs/deployment_and_config.md` | 部署与配置 — 服务器地址、账号密码、部署步骤、运维命令 |
1244	| `docs/project_module_handover.md` | Project 模块交接 — 单个模块的详细交接说明 |
1245	| **`docs/architecture_design.md`** (本文档) | **架构设计 — 系统架构全景、设计决策、扩展机制、技术细节** |
1246	
1247	---
1248	
1249	*文档2026-05-27*
1250	
1251	

[End of file.]