# WordPress AI Ops 项目交接

这是从 GEOFlow 拆出的独立 Laravel WordPress 内容运营系统。

## 当前重点

- 每日批次默认 4 篇，支持热点聚合、热点来源、AI 标题候选、标题库、去重、任务重试。
- 知识库检索、证据快照和带备注图片匹配。
- 写作 AI 生成文章，审核 AI 独立审核文章，必要时转人工。
- WordPress REST API 默认写入草稿，任务可选择待审核或直接发布，浏览器兜底默认关闭。
- `/admin/settings` 是前端配置中心。
- `/admin/tasks` 是自动运营任务配置中心：可设置热点关键词、时间范围、最低来源可信度、写作/审核 Skill、每日数量、运行时间和 WordPress 站点。
- 系统预设了 GEOFlow 印象文生成、GEOFlow 榜单文生成，以及事实/证据和结构/发布风险两段审核 Skill。

图片解析当前包括真实 MIME 类型、宽高、中文 alt、说明、关键词、场景和 OCR 文本。文章生成会把这些信息用于图片匹配，并在正文插入带 alt 的图片。当前没有把外部视觉模型识别宣称为已完成能力；后续接入时应使用异步任务，记录解析状态、模型版本和失败原因，并保留用户手写信息。

## AI 配置规则

`/admin/settings` 只保留两个 AI 配置卡片：生成 AI 和审核 AI。每张卡独立选择服务商、接口 URL、API 密钥和模型，保存后分别写入写作或审核任务路由。

当前可选服务商包括 GPT/OpenAI、DeepSeek、豆包和 Claude/Anthropic。“获取模型”必须携带当前卡片的接口 URL 和 API 密钥，请求该服务商真实的 `/models` 接口；认证失败、URL 错误或响应没有模型时必须报错，不能回退到本地假列表。保存时会再次请求并校验所选模型 ID 确实来自该服务商，用户不能手填模型 ID。因此可以配置 GPT 生成、DeepSeek 审核，也可以让同一服务商承担两个角色。

新批次创建文章时会保存当前写作 Skill 和二段审核 Skill 的版本快照；审核任务会连续调用审核 AI 两次，只有两段都通过且没有冲突、缺失证据或图片问题时才会进入通过状态。

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

页面：`/admin`、`/admin/settings`、`/admin/batches`、`/admin/reviews`、`/admin/wordpress`、`/admin/sources`、`/admin/titles`、`/admin/knowledge`、`/admin/images`、`/health`。

## 本轮已落地的后台操作

- 知识库：创建、启停、TXT/Markdown 上传、自动分片和重复资料去重。
- 图片库：创建、启停、图片上传、必填 alt、关键词/场景备注和图片信息更新。
- 标题库：单条添加、逐行批量导入、重复标题跳过、分类/优先级和启停。
- 热点来源：RSS/JSON/HTML 来源创建、启停、来源测试预览、立即抓取、抓取中/成功/失败状态、HTTP 状态和错误信息展示。系统任务还可使用预设热点聚合源。
- 热点标题：热点进入选题池后，生成 AI 为每条热点生成 3 个事实边界内的标题候选，批次详情支持人工选用，候选记录保存原始热点快照并避免重复使用。
- 内容批次：按日期创建、指定目标数量、运行生成、失败项重试、批次状态统计。
- 文章审核：按状态筛选，查看正文、来源、知识库证据和图片 alt，批准、转人工、重试和写入 WordPress 草稿。
- WordPress：新增站点、编辑站点、保留原应用密码、REST 连接测试和错误分类，密码不会回显。
- 导航栏：待审核、失败项和热点来源错误数量 badge。

所有后台 POST 操作同时支持普通页面重定向和 `Accept: application/json` JSON 响应，适合浏览器操作和后续脚本调用。

## 上线前仍需处理

1. 增加管理员登录和权限中间件，保护配置、Skill 和发布操作。
2. 生产环境使用队列 worker 执行批次、抓取、AI 标题生成、文章生成、审核和 WordPress 写稿任务；本地 demo 可使用 `QUEUE_CONNECTION=sync`。
3. 增加队列进度、失败告警、调用次数和费用统计。
4. 发布前用真实 WordPress 站点做一次 REST 连接、草稿回读和图片上传验证。

## 验证

```powershell
php artisan test
php artisan route:list --path=admin
php artisan view:cache
```

当前回归结果：87 个测试、513 个断言通过。当前环境的 `schedule:list` 若仍读取 PostgreSQL 缓存配置会失败，部署前请确保 `DB_CONNECTION`、`CACHE_STORE` 和队列连接都指向实际可用服务。

## 边界

不要提交 `.env`、Token、本地数据库、`vendor` 或外层 GEOFlow 文件。生产环境默认保持“审核通过后写 WordPress 草稿”。
