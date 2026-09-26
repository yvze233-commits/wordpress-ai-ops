# 第一阶段验收报告

## 验收范围

- 项目位于 `wordpress-ai-ops`，与 GEOFlow 主应用分离。
- 每日文章目标默认 4 篇，可在任务模式中调整。
- 默认将审核通过的文章写入 WordPress 草稿；待审核和直接发布由任务配置决定。

## 已验证能力

| 能力 | 验证证据 |
| --- | --- |
| 每日批次、四篇目标和重复选题拦截 | `DailyBatchTest`、`TopicSelectionServiceTest` |
| RSS、JSON、HTML 热点来源抓取 | `TopicCollectionTest` |
| 知识库上传、分片和证据哈希校验 | `KnowledgeUploadTest`、`KnowledgeRetrievalServiceTest` |
| 图片备注匹配和带 alt 的 HTML 图片 | `ImageMatchingServiceTest`、`ImagePlacementTest` |
| 图片 MIME、宽高读取和安全预览 | `ImagePreviewTest`、`ContentSchemaTest` |
| 内置 Skill、用户 Skill 校验和版本快照 | `SkillValidatorTest`、`AdminSkillUploadTest` |
| 结构化文章生成和第二个 AI 审核 | `ArticlePromptBuilderTest`、`ContentPipelineTest` |
| 人工审核和状态保护的草稿操作 | `AdminOperationsPagesTest`、`AdminReviewWorkflowTest` |
| WordPress REST 健康检查、媒体、草稿和幂等更新 | `WordPressRestClientTest`、`WordPressDraftPublishingTest` |
| 任务启动、暂停、运行和热点策略 | `AdminTaskModeTest`、`HotTopicAggregatorTest` |
| 重试策略、每日调度和过期运行回收 | `RetryPolicyTest`、`SchedulingTest` |
| 浏览器兜底开关和测试契约 | `BrowserFallbackPublisherTest`、`playwright/wordpress-draft-publisher.spec.ts` |

## 本地回归

```text
87 个测试，513 个断言，全部通过
git diff --check：通过
```

完整测试命令：

```powershell
php artisan test
php artisan route:list --path=admin
php artisan view:cache
```

Playwright 测试只有在显式提供 `WP_TEST_URL`、`WP_TEST_USERNAME` 和 `WP_TEST_PASSWORD` 时才运行。

## 图片解析边界

本阶段已验证图片真实 MIME、宽高、用户备注、关键词、场景、OCR 文本和正文匹配。自动视觉模型识别尚未作为已完成能力对外承诺；接入视觉模型时应使用异步任务，并记录解析状态、模型版本、提示词版本和失败原因。

## 上线前检查

1. 配置管理员登录和权限中间件。
2. 使用专用 WordPress 账号完成 REST 健康检查、媒体上传和草稿回读。
3. 生产环境将队列从 `sync` 切换到可持久化队列，并配置 worker、失败告警和重试策略。
4. 确认 `.env`、Token、本地数据库、`vendor` 和运行日志没有进入 Git。
