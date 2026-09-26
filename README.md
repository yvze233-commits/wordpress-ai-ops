# WordPress AI Ops

这是一个从 GEOFlow 拆出的独立 Laravel 项目，用于 WordPress 内容自动化运营。

系统围绕“选题 → 知识库检索 → 图片匹配 → AI 写作 → 两段 AI 审核 → WordPress 写入”运行。默认每天生成 4 篇文章，也可以在任务模式中调整数量、写作规则、审核规则、热点策略和 WordPress 写入方式。已经使用过的标题会被记录并去重。

## 已实现功能

- **任务模式**：启动、暂停和运行自动运营任务，配置每日文章数量、运行时间、热点关键词、来源可信度、写作 Skill、审核 Skill 和目标 WordPress 站点。
- **热点与标题**：支持 RSS、JSON、HTML 来源，支持来源预览、立即抓取、失败状态记录、热点聚合和 AI 标题候选。
- **知识库**：上传 TXT/Markdown 文件，自动清洗、分片、去重，并为文章生成保存证据快照。
- **图片库**：上传 JPG、PNG、WebP、GIF，读取真实 MIME 类型和宽高，保存中文 alt、说明、关键词、适用分类、场景和 OCR 文本；文章生成时按正文语义匹配图片并插入带 alt 的 HTML。
- **AI 配置**：只保留“生成 AI”和“审核 AI”两个角色卡片。每个角色独立配置服务商、接口 URL、API 密钥和模型，获取模型时请求真实的 `/models` 接口，不使用假模型列表。
- **两段审核**：审核 AI 连续执行事实与证据审核、结构与发布风险审核。缺少来源、图片或 alt 时会进入人工复核。
- **WordPress 写入**：支持 REST API 健康检查、媒体上传、草稿、待审核和直接发布；默认是草稿。浏览器兜底默认关闭。
- **人工操作**：支持批次详情、失败重试、文章预览、审核、人工复核和写入 WordPress。

## 图片解析说明

当前图片处理分为三层：

1. 上传时由服务层复核 MIME 类型，并读取宽高，拒绝非支持格式。
2. 用户可以填写 alt、图片说明、关键词、适用分类、场景和 OCR 文本，这些信息会保存到图片库。
3. 文章生成时根据标题、正文段落、分类、场景、关键词和 OCR 文本进行匹配，选中的图片会插入正文，并生成安全转义的 `alt` 和图片说明。

当前版本没有把外部视觉模型调用伪装成“已完成的自动识图”。如果后续接入视觉模型，应新增异步解析任务、解析状态、模型和版本记录，并保留用户手写的 alt 与备注。

## 本地运行

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = (Join-Path (Get-Location) 'database/database.sqlite')
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'sync'
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8090
```

打开 `http://127.0.0.1:8090/admin`。

主要页面：`/admin`、`/admin/tasks`、`/admin/settings`、`/admin/sources`、`/admin/titles`、`/admin/knowledge`、`/admin/images`、`/admin/batches`、`/admin/reviews`、`/admin/wordpress`、`/health`。

## 配置 AI

在 `/admin/settings` 中分别配置生成 AI 和审核 AI：

1. 选择服务商。
2. 确认接口 URL。
3. 填写 API 密钥。
4. 点击“获取模型”，系统请求服务商真实模型接口。
5. 从返回列表中选择模型并保存。

认证失败、接口地址错误或返回空模型列表时会直接报错，不会回退到本地预设模型。API 密钥使用加密字段保存，不会回显，也不会写入日志或测试响应。

## WordPress 自动化

建议先把任务的写入方式设为“草稿”，完成 REST 健康检查、图片上传和草稿回读后，再根据任务需要切换为“待审核”或“直接发布”。生产环境应使用 HTTPS、专用 WordPress 操作账号和应用密码。

## 验证命令

```powershell
php artisan test
php artisan route:list --path=admin
php artisan view:cache
git diff --check
```

当前版本本地回归结果：87 个测试、513 个断言通过。

## 安全边界

- 不要提交 `.env`、API 密钥、WordPress 应用密码、本地 SQLite 数据库、`vendor` 或运行日志。
- 默认只写 WordPress 草稿，直接发布必须由任务单独选择。
- 浏览器兜底仅用于受控测试账号，不能直接指向生产账号。
- 图片预览和上传只允许受控存储路径，不能把任意本地文件暴露为媒体。

## 后续工作

1. 增加管理员登录、权限和操作审计。
2. 为图片库增加异步视觉解析任务，并记录解析状态、模型版本和失败原因。
3. 使用队列 worker 执行热点抓取、AI 标题、文章生成、审核和 WordPress 写入。
4. 增加队列进度、调用次数、费用统计和失败告警。
