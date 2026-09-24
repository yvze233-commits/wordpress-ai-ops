# WordPress AI Ops 项目交接

这是从 GEOFlow 拆出的独立 Laravel WordPress 内容运营系统。

## 当前重点

- 每日批次默认 4 篇，支持热点来源、标题库、去重、任务重试。
- 知识库检索和带备注图片匹配。
- 写作 AI 生成文章，审核 AI 独立审核文章，必要时转人工。
- WordPress REST API 默认写入草稿，浏览器兜底默认关闭。
- `/admin/settings` 是前端配置中心。
- 系统预设了 GEOFlow 印象文生成、GEOFlow 榜单文生成，以及事实/证据和结构/发布风险两段审核 Skill。

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

页面：`/admin`、`/admin/settings`、`/health`。

## 下一步

1. 增加管理员登录和权限中间件，保护配置、Skill 和发布操作。
2. 增加 AI 与 WordPress 连接测试按钮和错误提示。
3. 补齐知识库、图片库、标题库和热点来源的上传/编辑表单。
4. 增加批次、生成、审核、图片匹配和发布的后台操作按钮。
5. 增加队列进度、失败告警、调用次数和费用统计。

## 边界

不要提交 `.env`、Token、本地数据库、`vendor` 或外层 GEOFlow 文件。生产环境默认保持“审核通过后写 WordPress 草稿”。
