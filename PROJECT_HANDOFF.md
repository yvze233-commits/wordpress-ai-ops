# WordPress AI Ops 项目交接

这是从 GEOFlow 拆出的独立 Laravel WordPress 内容运营系统。

## 当前重点

- 每日批次默认 4 篇，支持热点来源、标题库、去重、任务重试。
- 知识库检索和带备注图片匹配。
- 写作 AI 生成文章，审核 AI 独立审核文章，必要时转人工。
- WordPress REST API 默认写入草稿，浏览器兜底默认关闭。
- `/admin/settings` 是前端配置中心。

## AI 配置规则

每个服务商只配置一次连接：服务商、接口地址、API 密钥保存在 `ai_connections` 表中。当前预设包括 GPT/OpenAI、DeepSeek、豆包和 Claude/Anthropic。

任务分配在同一页面完成：

- 文章写作：选择一个已配置连接，再选择该服务商的模型预设。
- 文章审核：选择一个已配置连接，再选择该服务商的模型预设。

因此可以配置 GPT 写作、DeepSeek 审核，也可以让豆包同时负责两项。模型只能从下拉预设选择，不能手填模型 ID。

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

页面：`/admin`、`/admin/settings`、`/health`。

## 下一步

1. 增加管理员登录和权限中间件，保护配置、Skill 和发布操作。
2. 增加 AI 与 WordPress 连接测试按钮和错误提示。
3. 补齐知识库、图片库、标题库和热点来源的上传/编辑表单。
4. 增加批次、生成、审核、图片匹配和发布的后台操作按钮。
5. 增加队列进度、失败告警、调用次数和费用统计。

## 边界

不要提交 `.env`、Token、本地数据库、`vendor` 或外层 GEOFlow 文件。生产环境默认保持“审核通过后写 WordPress 草稿”。
