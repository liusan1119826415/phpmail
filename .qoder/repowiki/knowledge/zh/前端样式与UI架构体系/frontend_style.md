## 1. 核心设计体系
该项目采用**多端分离、组件库驱动**的混合前端架构。不同业务场景（管理后台、移动端商城、企业官网）使用独立的UI技术栈，缺乏统一的跨端设计系统（Design System），但通过静态资源目录进行了物理隔离。

### 1.1 技术栈分布
- **管理后台 (Admin)**: 基于 **Bootstrap 3** 布局框架，深度集成 **Element UI** (v2.x) 作为核心交互组件库。辅以 Font Awesome 图标库和 SweetAlert 弹窗样式。
- **移动端商城 (H5/SPA)**: 采用 **Vue.js 2.x** 生态，主要依赖 **Vant** 和 **Mint UI** 组件库。同时引入了 **YDUI** 用于处理复杂的地址选择等原生体验场景。
- **企业官网 (Official)**: 采用传统的 **原生 CSS + Flexbox** 布局，无大型框架依赖，强调轻量级展示。

## 2. 关键文件与资源
- **全局样式**: `static/css/common.css` (后台基础样式), `static/css/bootstrap.min.css` (布局基石)。
- **主题配置**: `addons/yun_shop/static/app/theme/theme.css` (定义了 `--themeBaseColor` 等 CSS 变量，支持简单的动态换肤)。
- **组件库资源**: 
  - `static/yunshop/element-ui/` (后台 UI 核心)
  - `addons/yun_shop/static/app/vant.css` & `mint-ui.css` (移动端 UI 核心)
  - `addons/yun_shop/static/app/ydui.px.css` (移动端增强 UI)
- **构建配置**: `webpack.mix.js` (存在但为空，表明项目目前主要依赖预编译的静态资源或 CDN 引用，未启用现代化的自动化构建流程)。

## 3. 架构约定与规范
### 3.1 样式组织
- **模块化隔离**: 样式文件按业务域存放，如 `static/yunshop/css/` 下包含 `order.css`, `member.css` 等业务专属样式。
- **覆盖策略**: 大量使用类名覆盖（Override）方式修改组件库默认样式（如 `.a-colour.css` 中自定义按钮颜色）。
- **响应式策略**: 
  - 后台主要依赖 Bootstrap 的栅格系统 (`row`, `col-*`)。
  - 移动端采用 `rem` 适配方案（见 `static/yunshop/app/detail.html` 中的 JS 动态计算 `font-size`）。

### 3.2 视觉一致性
- **色彩体系**: 后台以 Bootstrap 默认蓝 (`#428bca`) 和 Element UI 默认色为主；移动端则根据 `theme.css` 中的变量进行局部定制。
- **字体规范**: 优先使用系统字体栈（Microsoft Yahei, Arial），在 `common.css` 中有明确定义。

## 4. 开发者指南
1. **严禁直接修改组件库源码**: 所有样式调整应通过新增 CSS 类并提高优先级（或使用 scoped 样式）来实现。
2. **移动端适配**: 开发 H5 页面时，需遵循 `rem` 基准值（通常为 16px * (clientWidth / 375)），确保在不同屏幕下的显示一致性。
3. **资源引用**: 由于缺乏自动化打包，新增样式文件需在对应的 Blade 模板或 HTML 入口文件中手动通过 `<link>` 标签引入。
4. **主题扩展**: 若需全局换肤，应优先修改 `theme.css` 中的 CSS 变量，而非硬编码颜色值。