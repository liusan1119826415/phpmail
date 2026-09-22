该云商多租户SaaS平台采用基于 **Laravel** 框架的混合配置系统，结合了静态文件配置、环境变量（`.env`）、外部PHP配置文件以及数据库驱动的动态设置。其核心设计目标是支持多租户隔离、插件化扩展以及高性能的运行时读取。

### 1. 配置加载层级与优先级
系统的配置加载遵循以下优先级顺序：
1.  **环境变量层 (`.env`)**：通过 Laravel 的 `env()` 函数加载基础环境参数（如 `APP_ENV`, `DB_HOST`, `REDIS_PASSWORD`）。
2.  **静态配置层 (`config/*.php`)**：位于 `config/` 目录下的 PHP 数组文件。这些文件会引用 `env()` 的值作为默认值或覆盖值。
3.  **外部配置层 (`database/config.php`)**：在非 `platform` 模式下，`config/database.php` 会强制引入根目录外的 `data/config.php` 或本地的 `database/config.php`。这是一个遗留的微擎（WeEngine）风格配置方式，用于存储敏感的数据库连接信息。
4.  **动态运行时层 (Database + Cache)**：通过 `Setting` Facade 从数据库表 `yz_setting` 中读取业务配置，并利用 Redis 进行多级缓存。

### 2. 核心组件与关键文件
- **`config/app.php`**: 应用入口配置，定义了服务提供者（Service Providers）、别名（Aliases）以及全局常量（如支付类型映射）。
- **`config/database.php`**: 复杂的数据库配置逻辑，支持主从读写分离、多库连接（`mysql`, `kefu`, `mongodb`），并根据 `APP_Framework` 环境变量动态切换配置源。
- **`app/yunshop.php`**: 定义了全局辅助类 `YunShop`，提供租户上下文获取（`YunShop::app()->uniacid`）、路由解析及权限判断逻辑。
- **`app/common/facades/Setting.php` & `app/common/models/Setting.php`**: 封装了业务配置的 CRUD 操作。支持按租户 ID (`uniacid`) 隔离配置，并自动处理序列化/反序列化。
- **`app/common/helpers/SettingCache.php`**: 实现了基于 Redis 的配置缓存机制，减少高频业务配置对数据库的查询压力。
- **`bootstrap/app.php`**: 应用程序实例化入口，绑定了自定义的 Kernel 和异常处理器。

### 3. 架构约定与设计决策
- **多租户隔离**：绝大多数业务配置都绑定在 `uniacid`（租户ID）上。`YunShopServiceProvider` 在启动时会根据请求上下文初始化 `Setting::$uniqueAccountId`。
- **双轨制数据库配置**：为了兼容旧版微擎架构，系统保留了 `database/config.php` 这种硬编码 PHP 数组的配置方式，同时也在 `.env` 中预留了标准 Laravel 数据库配置项。
- **插件化配置注入**：通过 `PluginServiceProvider` 动态加载插件目录下的配置，实现了功能的即插即用。
- **缓存策略**：业务配置采用“内存+Redis”二级缓存。`SettingCache` 会在每次请求开始时尝试从 Redis 拉取全量配置到内存数组中，后续读取直接从内存获取。

### 4. 开发者规范
- **敏感信息处理**：严禁在 `config/` 下的文件中硬编码密码或密钥。必须使用 `env('KEY', 'default')` 格式，并确保 `.env` 文件不被提交至版本控制系统。
- **配置读取方式**：
  - 基础框架配置使用 `config('app.key')`。
  - 业务动态配置（如商城开关、营销规则）必须使用 `Setting::get('group.key')`。
- **环境区分**：通过 `APP_Framework` 环境变量区分独立部署模式（`false`）与平台模式（`platform`），这将决定配置文件的加载路径。
- **缓存刷新**：修改 `config/` 文件后需执行 `php artisan config:cache`；修改数据库中的 `Setting` 配置时，系统会自动更新 Redis 缓存，但需注意缓存穿透问题。