## 1. 核心架构与策略

该系统基于 **Laravel** 框架构建，采用**自定义异常类 + 全局异常处理器**的模式进行错误管理。核心设计思想是将业务逻辑中的错误通过抛出特定的 `Exception` 子类来中断流程，并由统一的 `Handler` 捕获，根据请求上下文（API、前端页面、后台管理）返回不同格式的响应（JSON 或 HTML 视图）。

### 主要特点：
- **分层异常体系**：以 `ShopException` 为基类，衍生出针对 API、支付、会员登录等场景的专用异常。
- **上下文感知渲染**：`Handler` 能够识别当前是 AJAX/API 请求还是普通页面请求，自动切换返回 JSON 错误码或重定向/错误页面。
- **静默报告机制**：通过 `$dontReport` 数组配置，避免将常见的业务异常（如登录失败、参数校验错误）写入服务器错误日志，保持日志清洁。

## 2. 关键文件与组件

| 文件路径 | 作用描述 |
| :--- | :--- |
| `app/common/exceptions/Handler.php` | **全局异常处理器**。继承自 Laravel 的 `ExceptionHandler`，负责异常的记录（report）和响应渲染（render）。 |
| `app/common/exceptions/ShopException.php` | **商城基础异常类**。所有业务异常的父类，支持携带额外数据 (`data`) 和重定向地址 (`redirect`)。 |
| `app/common/exceptions/AppException.php` | **通用应用异常**。通常用于控制器或服务层抛出的通用业务错误。 |
| `app/common/exceptions/ApiException.php` | **接口异常**。专门用于 API 调用失败的场景，确保返回标准的 JSON 格式。 |
| `app/common/exceptions/PaymentException.php` | **支付异常**。定义了支付密码错误、未设置密码等特定错误码及链式调用方法。 |
| `app/common/exceptions/MemberNotLoginException.php` | **会员未登录异常**。触发时会自动处理 Session 清除并返回登录跳转链接或状态码。 |
| `app/payment/OrderPayException.php` | **支付回调异常处理**。一个特殊的工具类，用于在支付回调中记录异常日志到数据库 (`PayCallbackException` 模型)，而非直接抛出中断。 |

## 3. 异常分类与约定

系统定义了一套层次分明的异常类，开发者应遵循以下约定：

- **ShopException**: 最通用的业务异常。构造函数支持传入 `$message`, `$data`, `$redirect`。
- **ApiException**: 当接口发生错误时抛出，`Handler` 会将其转换为包含 `error_code` 和 `error_msg` 的 JSON 响应。
- **MemberNotLoginException**: 用于权限拦截。在 API 中返回 `login_status: 0` 及登录 URL；在页面中可能触发重定向。
- **PaymentException**: 包含预定义的错误码常量（如 `PAY_PASSWORD_ERROR = 2003`），并提供 `passwordError()` 等便捷方法来快速构建异常实例。
- **NotFoundException**: 继承自 Symfony 的 `NotFoundHttpException`，用于处理 404 场景。

## 4. 错误码与日志规范

- **错误码定义**：部分错误码硬编码在异常类中（如 `PaymentException`），部分定义在 `ErrorCode.php` 和 `ErrorConst.php` 中。例如：
  - `UNI_ACCOUNT_NOT_FOUND = 3001`
  - `ORDER_CLOSE = 1004`
- **日志记录**：
  - `Handler::report()` 方法会自动记录未被 `$dontReport` 过滤的异常。
  - 生产环境下，系统曾集成远程错误上报（代码中已注释），目前主要依赖本地 `Log::error()`。
  - 支付回调等关键业务的异常会通过 `OrderPayException::saveErrorException()` 持久化到数据库，便于后续对账和排查。

## 5. 开发者指南

1. **抛出异常**：在业务逻辑校验失败时，优先使用 `throw new AppException('错误信息')` 或更具体的子类。
2. **避免直接返回**：不要在 Service 层直接返回 `errorJson()`，而应抛出异常，让 Controller 或 Middleware 统一处理。
3. **API 响应**：如果希望 API 返回特定的错误结构，请使用 `ApiException` 或在 `ShopException` 中传入 `$data` 数组。
4. **登录态检查**：涉及会员权限的操作，若未登录应抛出 `MemberNotLoginException`，以便前端统一拦截并引导登录。