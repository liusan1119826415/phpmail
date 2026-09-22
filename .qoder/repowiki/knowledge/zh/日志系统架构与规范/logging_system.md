## 1. 核心框架与组件
- **基础框架**: 基于 Laravel 默认的 `Monolog` 日志库，通过 `config/logging.php` 进行全局通道配置。
- **自定义封装**: 项目在 `app/framework/Log/` 目录下建立了一套分层日志体系，核心基类为 `BaseLog`，并派生出多个业务专用日志类（如 `SimpleLog`, `CronLog`, `ErrorLog`, `SqlLog` 等）。
- **门面代理**: 提供了自定义的 `app\framework\Support\Facades\Log`，在继承原生 Facade 的基础上增加了针对特定业务（如订单、调试、错误）的快速访问方法。

## 2. 关键文件与目录
- **配置文件**: `config/logging.php` —— 定义默认通道（stack/daily）及保留天数。
- **核心逻辑**: 
  - `app/framework/Log/BaseLog.php`: 所有自定义日志的父类，负责初始化 `RotatingFileHandler`（按天轮转）和格式化器。
  - `app/framework/Log/SimpleLog.php`: 通用日志实现，支持动态指定文件名。
  - `app/framework/Log/TraceLog.php`: 调试追踪日志，通过 URL 参数 `debug_log` 动态开启，直接写入 `storage/logs/trace/`。
- **业务集成**: 
  - `app/common/providers/AppServiceProvider.php`: 在服务启动时注入 `CronLog` 到定时任务管理器，并注册 `TraceLog` 单例。
  - `app/process/QueueKeeper.php`: 队列守护进程使用 `SimpleLog` 记录任务生命周期（开始、结束、异常）。

## 3. 架构设计与约定
- **分模块存储**: 摒弃单一日志文件，采用“业务域”隔离策略。不同模块（如订单、队列、定时任务、SQL）拥有独立的日志文件和目录。
- **自动轮转**: 所有基于 `BaseLog` 的日志均启用 `RotatingFileHandler`，默认保留 7 天（可通过后台设置 `website_log.day` 动态调整）。
- **结构化输出**: 使用 `LineFormatter` 并开启上下文信息输出，确保数组类型的日志内容能被完整记录。
- **调试开关**: `TraceLog` 提供了一种生产环境下的临时调试手段，仅当请求携带特定 GET 参数时才记录详细追踪信息，避免性能损耗。

## 4. 开发者规范
- **通用日志**: 对于普通业务逻辑，直接使用 Laravel 原生的 `Log::info()`, `Log::error()` 等，日志将写入 `storage/logs/laravel.log`。
- **专用日志**: 
  - **订单相关**: 使用 `app('OrderManager')->log` 或 `Log::order()`。
  - **队列/进程**: 在 Process 类中实例化 `new SimpleLog('queueKeeper')` 或 `new SimpleLog('pid')`。
  - **定时任务**: 使用 `new CronLog()`。
- **调试建议**: 在生产环境排查问题时，可通过添加 `?debug_log=*` 或 `?debug_log=["coupon"]` 到 URL 来激活 `TraceLog`，日志将实时写入 `storage/logs/trace/` 目录。
- **禁止事项**: 严禁在代码中使用 `echo`, `print_r`, `var_dump` 进行调试；严禁直接操作文件系统写入日志，必须通过封装好的 Log 类以确保轮转机制生效。