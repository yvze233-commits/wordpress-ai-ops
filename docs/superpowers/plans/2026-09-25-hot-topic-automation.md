# 热点驱动 WordPress 自动运营实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将热点采集、时讯标题生成、每日任务、AI 审核和 WordPress 写入串成可暂停、可重试、可审计的自动运营链路。

**Architecture:** 保留现有 Laravel Admin 控制器、Eloquent 模型、队列 Job 和 Blade 页面。新增统一的热点聚合服务和标题候选服务，沿用 `TopicFeed`/`TopicCandidate` 作为事实来源池，新增标题候选表保存 AI 产物；任务显式绑定 WordPress 站点，审核通过后按任务写入状态执行。

**Tech Stack:** Laravel 12、PHP 8.4、SQLite、Eloquent、Laravel HTTP Client、Blade、PHPUnit。

---

### Task 1: 让热点抓取真正可执行且可观察

**Files:**
- Modify: `app/Http/Controllers/Admin/TopicSourceController.php`
- Modify: `app/Jobs/CollectTopicSourceJob.php`
- Modify: `app/Domain/Topics/AbstractTopicSourceConnector.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/sources/index.blade.php`
- Test: `tests/Feature/TopicCollectionTest.php`
- Test: `tests/Feature/AdminAssetManagementTest.php`

- [ ] **Step 1: Write failing tests for source preview and direct local execution**

  在 `TopicCollectionTest` 增加：

  ```php
  public function test_source_preview_returns_first_five_parsed_items_without_writing_candidates(): void
  {
      Http::fake(['https://example.test/feed.xml' => Http::response('<rss><channel><item><guid>1</guid><title>热点一</title><link>https://example.test/1</link></item></channel></rss>')]);
      $source = TopicSource::create(['name' => '预览源', 'type' => 'rss', 'url' => 'https://example.test/feed.xml']);

      $response = $this->postJson("/admin/sources/{$source->id}/preview");

      $response->assertOk()->assertJsonPath('data.0.title', '热点一');
      $this->assertDatabaseCount('topic_candidates', 0);
  }
  ```

  在 `AdminAssetManagementTest` 增加：当 `QUEUE_CONNECTION=sync` 时，调用“立即抓取”后断言来源状态为 `active` 且 `feeds_count` 增加；数据库队列模式仍只断言 Job 被派发。

- [ ] **Step 2: Run focused tests and verify they fail**

  Run: `php artisan test --filter='TopicCollectionTest|AdminAssetManagementTest' --no-coverage`

  Expected: FAIL because the preview route and execution/readback behavior do not exist.

- [ ] **Step 3: Implement preview and execution state**

  在 `TopicSourceController` 增加 `preview()`，调用连接器收集并只返回 `array_slice($items, 0, 5)`，异常返回 `422` 且包含 `code`、`message`。增加 `source` 的 `fetching` 状态：派发前设置 `fetching`；`CollectTopicSourceJob` 成功后改回 `active`，失败保留 `error` 或 `paused`。

  路由增加：

  ```php
  Route::post('/admin/sources/{topicSource}/preview', [TopicSourceController::class, 'preview'])->name('admin.sources.preview');
  ```

  `AbstractTopicSourceConnector::request()` 增加固定的浏览器式 `User-Agent` 和 `Accept` 头，同时保留现有超时、连接超时及响应大小限制。

  页面为每个来源增加“测试来源”按钮、抓取状态文案、最近成功时间和前一条错误；测试按钮只读，不创建 `TopicFeed` 或 `TopicCandidate`。

- [ ] **Step 4: Run focused tests and verify they pass**

  Run: `php artisan test --filter='TopicCollectionTest|AdminAssetManagementTest' --no-coverage`

  Expected: PASS.

- [ ] **Step 5: Commit**

  ```powershell
  git add app/Http/Controllers/Admin/TopicSourceController.php app/Jobs/CollectTopicSourceJob.php app/Domain/Topics/AbstractTopicSourceConnector.php routes/web.php resources/views/admin/sources/index.blade.php tests/Feature/TopicCollectionTest.php tests/Feature/AdminAssetManagementTest.php
  git commit -m "feat: make topic collection observable"
  ```

### Task 2: 增加系统预设热点聚合器和热点策略

**Files:**
- Create: `app/Domain/Topics/HotTopicAggregator.php`
- Create: `app/Jobs/AggregateHotTopicsJob.php`
- Create: `database/migrations/2026_09_25_090000_add_hot_topic_strategy_to_content_tasks.php`
- Modify: `app/Models/ContentTask.php`
- Modify: `app/Http/Controllers/Admin/TaskController.php`
- Modify: `app/Domain/Topics/TopicSelectionService.php`
- Modify: `config/content-ops.php`
- Modify: `resources/views/admin/tasks/index.blade.php`
- Test: `tests/Unit/HotTopicAggregatorTest.php`
- Test: `tests/Feature/AdminTaskModeTest.php`

- [ ] **Step 1: Write failing tests for aggregation and strategy persistence**

  `HotTopicAggregatorTest` 使用 `Http::fake()` 配置两个预设 RSS 地址，断言聚合器只保留窗口内项目、按 URL/事件指纹去重并保留来源可信度。`AdminTaskModeTest` 断言任务保存 `topic_window_hours`、`topic_keywords`、`min_source_trust` 和 `wordpress_connection_id`。

- [ ] **Step 2: Run tests to verify they fail**

  Run: `php artisan test --filter='HotTopicAggregatorTest|AdminTaskModeTest' --no-coverage`

  Expected: FAIL because the strategy columns, model fields and aggregator do not exist.

- [ ] **Step 3: Add strategy migration and model casts**

  迁移向 `content_tasks` 增加：

  ```php
  $table->unsignedSmallInteger('topic_window_hours')->default(72);
  $table->text('topic_keywords')->nullable();
  $table->unsignedTinyInteger('min_source_trust')->default(50);
  $table->foreignId('wordpress_connection_id')->nullable()->constrained('wordpress_connections')->nullOnDelete();
  ```

  `ContentTask::$fillable`、`$attributes`、`casts()` 同步增加这些字段；`TaskController` 验证关键词长度、时间范围 `1..168`、可信度 `0..100`，并在页面用文本框和站点下拉选择，不让用户输入模型 ID。

- [ ] **Step 4: Implement the aggregator**

  `HotTopicAggregator` 从 `config('content-ops.hot_topic_sources')` 读取预设来源，调用现有 RSS connector，过滤 `published_at >= now()->subHours($task->topic_window_hours)`、关键词不匹配和可信度低于任务阈值的项目，再按发布时间降序、可信度降序返回。通过 `TopicFingerprint` 和 `TopicNormalizer` 去重，输出统一数组，不直接写数据库。

  `AggregateHotTopicsJob` 逐个预设来源运行聚合器，复用 `CollectTopicSourceJob` 的落库格式，写入 `TopicFeed`/`TopicCandidate` 并记录 `hot_topics_aggregated` 审计事件；单个来源失败不能阻断其他来源。

- [ ] **Step 5: Run focused tests and cache the view**

  Run: `php artisan migrate:fresh --env=testing && php artisan test --filter='HotTopicAggregatorTest|AdminTaskModeTest' --no-coverage && php artisan view:cache`

  Expected: PASS.

- [ ] **Step 6: Commit**

  ```powershell
  git add app/Domain/Topics/HotTopicAggregator.php app/Jobs/AggregateHotTopicsJob.php database/migrations/2026_09_25_090000_add_hot_topic_strategy_to_content_tasks.php app/Models/ContentTask.php app/Http/Controllers/Admin/TaskController.php app/Domain/Topics/TopicSelectionService.php config/content-ops.php resources/views/admin/tasks/index.blade.php tests/Unit/HotTopicAggregatorTest.php tests/Feature/AdminTaskModeTest.php
  git commit -m "feat: add hot topic aggregation strategy"
  ```

### Task 3: 实现 AI 标题候选和人工选择

**Files:**
- Create: `database/migrations/2026_09_25_091000_create_topic_title_candidates_table.php`
- Create: `app/Models/TopicTitleCandidate.php`
- Create: `app/Domain/Topics/TopicTitleCandidateService.php`
- Create: `app/Jobs/GenerateTopicTitleCandidatesJob.php`
- Modify: `app/Domain/Content/ArticlePromptBuilder.php`
- Modify: `app/Domain/Content/StructuredAiGateway.php`
- Modify: `app/Jobs/CreateDailyBatchJob.php`
- Modify: `app/Models/TopicCandidate.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/BatchController.php`
- Modify: `resources/views/admin/batches/show.blade.php`
- Test: `tests/Unit/TopicTitleCandidateServiceTest.php`
- Test: `tests/Feature/AdminBatchOperationsTest.php`

- [ ] **Step 1: Write failing tests for title schema, deduplication and selection**

  使用 fake `StructuredAiGateway` 返回三个标题，断言服务保存三条候选、保留 `topic_candidate_id` 和原始来源、跳过已使用标题；批次详情 POST 选择标题后只允许选择本热点的未使用候选。

- [ ] **Step 2: Run tests to verify they fail**

  Run: `php artisan test --filter='TopicTitleCandidateServiceTest|AdminBatchOperationsTest' --no-coverage`

  Expected: FAIL because the table, service, job and routes do not exist.

- [ ] **Step 3: Add title candidate schema and service**

  表字段：`topic_candidate_id`、`title`、`rationale`、`source_snapshot`、`score`、`status`、`used_at`、时间戳；唯一索引为 `topic_candidate_id + normalized_title`。状态限定 `available/selected/used/rejected`。

  `TopicTitleCandidateService::generate()` 的 prompt 必须包含原始标题、摘要、来源 URL、发布时间和任务关键词；AI 输出严格为 3 个标题，标题超长、空标题、事实外推或与已用标题相似时过滤，并记录失败审计。

- [ ] **Step 4: Wire title generation into batch creation**

  `CreateDailyBatchJob` 在选择热点后派发 `GenerateTopicTitleCandidatesJob`；任务批次使用已有候选时优先选择 `selected`，否则选择评分最高的 `available` 候选；标题库补足时不生成无来源热点标题。

  批次详情增加标题候选列表和“选用此标题”动作，动作写入 `selected` 和审计事件，并同步文章的标题字段。

- [ ] **Step 5: Run tests and commit**

  Run: `php artisan migrate:fresh --env=testing && php artisan test --filter='TopicTitleCandidateServiceTest|AdminBatchOperationsTest' --no-coverage`

  Expected: PASS.

  ```powershell
  git add database/migrations/2026_09_25_091000_create_topic_title_candidates_table.php app/Models/TopicTitleCandidate.php app/Domain/Topics/TopicTitleCandidateService.php app/Jobs/GenerateTopicTitleCandidatesJob.php app/Domain/Content/ArticlePromptBuilder.php app/Domain/Content/StructuredAiGateway.php app/Jobs/CreateDailyBatchJob.php app/Models/TopicCandidate.php routes/web.php app/Http/Controllers/Admin/BatchController.php resources/views/admin/batches/show.blade.php tests/Unit/TopicTitleCandidateServiceTest.php tests/Feature/AdminBatchOperationsTest.php
  git commit -m "feat: generate hot topic title candidates"
  ```

### Task 4: 串联每日自动任务和队列调度

**Files:**
- Modify: `app/Console/Commands/ContentOpsCreateDailyBatch.php`
- Modify: `routes/console.php`
- Modify: `app/Jobs/CreateDailyBatchJob.php`
- Modify: `app/Jobs/ReviewContentItemJob.php`
- Modify: `app/Jobs/PublishWordPressDraftJob.php`
- Modify: `app/Models/ContentRun.php`
- Test: `tests/Feature/DailyBatchTest.php`
- Test: `tests/Feature/SchedulingTest.php`

- [ ] **Step 1: Write failing tests for task schedule, shortfall and idempotency**

  测试运行中的任务只创建一个当天批次；暂停任务不创建新批次；热点不足时批次状态为 `shortfall` 且不生成无来源文章；同一任务同一天重复执行不会重复派发生成 Job。

- [ ] **Step 2: Run tests to verify they fail**

  Run: `php artisan test --filter='DailyBatchTest|SchedulingTest' --no-coverage`

  Expected: FAIL on schedule filtering, shortfall fallback or duplicate dispatch behavior.

- [ ] **Step 3: Implement scheduler and queue idempotency**

  在 `routes/console.php` 每分钟扫描 `status=running` 且 `schedule_time` 匹配当前分钟的任务，使用 `withoutOverlapping()`，派发 `CreateDailyBatchJob`。`CreateDailyBatchJob` 使用任务 ID + 日期唯一查询并在事务内锁定批次；对每个内容项只在没有生成运行记录时派发 `GenerateContentItemJob`。

  将 `ReviewContentItemJob`、`PublishWordPressDraftJob` 的 WordPress 连接选择改为优先使用 `content_tasks.wordpress_connection_id`，仅在为空时选择 `healthy/active` 站点；无站点时保留审核通过状态并写入可重试审计事件。

- [ ] **Step 4: Run focused tests and commit**

  Run: `php artisan test --filter='DailyBatchTest|SchedulingTest|ContentPipelineTest|WordPressDraftPublishingTest' --no-coverage`

  Expected: PASS.

  ```powershell
  git add app/Console/Commands/ContentOpsCreateDailyBatch.php routes/console.php app/Jobs/CreateDailyBatchJob.php app/Jobs/ReviewContentItemJob.php app/Jobs/PublishWordPressDraftJob.php app/Models/ContentRun.php tests/Feature/DailyBatchTest.php tests/Feature/SchedulingTest.php
  git commit -m "feat: schedule automated content tasks"
  ```

### Task 5: 完善 WordPress 自动发布调试和失败恢复

**Files:**
- Modify: `app/Infrastructure/WordPress/WordPressRestClient.php`
- Modify: `app/Http/Controllers/Admin/WordPressConnectionController.php`
- Modify: `resources/views/admin/wordpress/index.blade.php`
- Modify: `app/Jobs/PublishWordPressDraftJob.php`
- Modify: `resources/views/admin/reviews/show.blade.php`
- Test: `tests/Feature/AdminWordPressConnectionTest.php`
- Test: `tests/Feature/WordPressDraftPublishingTest.php`

- [ ] **Step 1: Write failing tests for readback and retry visibility**

  增加 REST 诊断测试：检查用户、分类、标签和文章写入权限的只读能力；发布测试断言保存 WordPress 返回状态、文章链接和错误信息；失败后断言文章仍可重试且不会创建第二篇文章。

- [ ] **Step 2: Run tests to verify they fail**

  Run: `php artisan test --filter='AdminWordPressConnectionTest|WordPressDraftPublishingTest' --no-coverage`

  Expected: FAIL because readback payload and UI retry details are not complete.

- [ ] **Step 3: Implement safe REST diagnostics and readback**

  在 `WordPressRestClient` 增加 `postsByMarker()` 和 `capabilities()`，诊断只读取 `/users/me`、`/categories`、`/tags`、`/posts?search=...&status=any`，不创建文章、不上传媒体。`PublishWordPressDraftJob` 在成功和失败的 `ContentRun` payload 中保存 HTTP 结果摘要、WordPress ID、URL、状态和重试次数，绝不保存密码或完整响应中的敏感字段。

  WordPress 页面增加“最近写入状态、文章 ID、链接、失败重试次数”；文章详情显示“重试发布”按钮，只有 `retryable_failed` 或已审核未写入状态可操作。

- [ ] **Step 4: Run tests and commit**

  Run: `php artisan test --filter='AdminWordPressConnectionTest|WordPressDraftPublishingTest' --no-coverage`

  Expected: PASS.

  ```powershell
  git add app/Infrastructure/WordPress/WordPressRestClient.php app/Http/Controllers/Admin/WordPressConnectionController.php resources/views/admin/wordpress/index.blade.php app/Jobs/PublishWordPressDraftJob.php resources/views/admin/reviews/show.blade.php tests/Feature/AdminWordPressConnectionTest.php tests/Feature/WordPressDraftPublishingTest.php
  git commit -m "feat: improve wordpress automation diagnostics"
  ```

### Task 6: 运营页面、审计和完整回归

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/admin/layout.blade.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `resources/views/admin/batches/index.blade.php`
- Modify: `resources/views/admin/batches/show.blade.php`
- Modify: `PROJECT_HANDOFF.md`
- Test: `tests/Feature/AdminOperationsPagesTest.php`
- Test: `tests/Feature/AdminAssetPagesTest.php`

- [ ] **Step 1: Add observable automation counters**

  导航和仪表盘显示：抓取失败、待生成标题、待审核文章、发布失败、今日任务批次和短缺批次数。所有查询使用 `withCount` 或聚合查询，不在 Blade 内查询模型。

- [ ] **Step 2: Add end-to-end feature coverage**

  测试从来源测试、热点入池、标题候选、批次创建、二段审核到 WordPress 草稿写入的最小成功链路；另测暂停任务、热点不足、来源失败、发布失败和直接发布状态。

- [ ] **Step 3: Run complete verification**

  ```powershell
  php artisan migrate:fresh --env=testing
  php artisan test --no-coverage
  php artisan view:cache
  php artisan route:list --path=admin
  git diff --check
  ```

  Expected: all tests pass, all required admin routes exist, Blade cache succeeds, and `git diff --check` has no output.

- [ ] **Step 4: Update handoff documentation**

  在 `PROJECT_HANDOFF.md` 增加热点策略字段、队列 worker 启动命令、来源测试入口、标题候选状态、任务绑定 WordPress 站点和生产环境默认草稿规则；明确真实 WordPress 站点和真实 AI 配置仍需在部署环境做一次端到端验证。

- [ ] **Step 5: Commit**

  ```powershell
  git add app/Providers/AppServiceProvider.php resources/views/admin/layout.blade.php resources/views/admin/dashboard.blade.php resources/views/admin/batches/index.blade.php resources/views/admin/batches/show.blade.php PROJECT_HANDOFF.md tests/Feature/AdminOperationsPagesTest.php tests/Feature/AdminAssetPagesTest.php
  git commit -m "feat: expose wordpress automation operations"
  ```

