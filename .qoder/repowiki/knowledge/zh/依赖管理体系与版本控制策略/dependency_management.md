该项目采用多语言混合的依赖管理方案，以 PHP (Composer) 为核心，辅以 JavaScript (NPM) 和 Python (pip) 工具链。

### 1. PHP 后端依赖管理 (Composer)
- **核心框架**: 基于 `laravel/framework` (v8.83.27)，支持 PHP 7.4+ 及 8.0+。
- **版本锁定**: 使用 `composer.lock` 严格锁定所有第三方库的版本（如 `guzzlehttp/guzzle` v6.5.8, `overtrue/wechat` v4.6.0），确保生产环境一致性。
- **镜像加速**: `composer.lock` 中配置了阿里云 Composer 镜像 (`mirrors.aliyun.com`) 作为首选下载源，以提升国内环境的安装速度。
- **自动加载**: 通过 `psr-4` 映射 `app/`, `business/`, `Database/` 等命名空间，并使用 `files` 字段引入全局辅助函数 `app/helpers.php` 和 `app/yunshop.php`。
- **插件集成**: 引入了 `easywechat-composer/easywechat-composer` 用于微信生态集成，并允许其插件执行。

### 2. 前端资源管理 (NPM & Laravel Mix)
- **构建工具**: 使用 `laravel-mix` (v6.0.49) 作为前端资源编译和打包工具。
- **核心库**: 仅显式声明了少量关键依赖，如 `three.js` (3D渲染) 和 `@tweenjs/tween.js` (动画补间)。
- **静态资源**: 大量前端业务代码（Vue.js SPA、管理后台 UI）直接存放在 `addons/yun_shop/static/` 目录下，未完全纳入 NPM 模块化管理体系，表现为传统静态文件引用模式。

### 3. 其他语言工具链
- **Python 工具**: 在 `tools/goods_import_client/` 目录下存在一个独立的商品导入客户端，通过 `requirements.txt` 管理 `requests` 和 `pyinstaller` 依赖，用于打包桌面端工具。

### 4. 开发者规范
- **禁止手动修改 vendor**: 所有 PHP 依赖必须通过 `composer install/update` 管理。
- **版本同步**: 提交代码时必须同步更新 `composer.lock` 和 `package-lock.json`，防止团队成员间依赖版本漂移。
- **环境配置**: 依赖 `.env` 文件管理敏感配置，而非硬编码在依赖或代码中。