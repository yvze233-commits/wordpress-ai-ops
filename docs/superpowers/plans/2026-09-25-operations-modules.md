# WordPress AI Ops 运营模块 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 将资产管理、批次、审核和 WordPress 发布连接从只读列表升级为可操作后台。

**Architecture:** 沿用现有 Admin 控制器、Eloquent 模型、领域服务和 Blade 页面。每个模块提供 HTML 页面和 `expectsJson()` JSON 响应；状态动作复用已有服务和状态转移，不在控制器中直接写业务 SQL。

**Tech Stack:** Laravel 12、PHP 8.4、SQLite、Eloquent、Blade、PHPUnit/Pest Feature tests。

---

### Task 1: 资产管理基础动作

**Files:**
- Create: `database/migrations/2026_09_25_070000_add_asset_management_fields.php`
- Modify: `app/Models/KnowledgeBase.php`, `app/Models/KnowledgeDocument.php`, `app/Models/ImageLibrary.php`, `app/Models/LibraryImage.php`, `app/Models/TitleLibraryEntry.php`, `app/Models/TopicSource.php`
- Modify: `app/Http/Controllers/Admin/KnowledgeController.php`, `ImageLibraryController.php`, `TitleLibraryController.php`, `TopicSourceController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminAssetManagementTest.php`

- [ ] Write failing tests for create/update/toggle/import validation and JSON responses.
- [ ] Add only required fields: `knowledge_bases.is_default`, `deleted_at` for title/image/document records where safe, and source fetch counters/error timestamps.
- [ ] Implement validated POST actions using existing `KnowledgeIngestionService` and `ImageIngestionService`; add title bulk import and source fetch dispatch.
- [ ] Add route names and HTML/JSON responses with flash/error messages.
- [ ] Run focused feature tests, then migrate SQLite.

### Task 2: Asset pages and navigation state

**Files:**
- Modify: `resources/views/admin/knowledge/index.blade.php`, `images/index.blade.php`, `titles/index.blade.php`, `sources/index.blade.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/layout.blade.php`
- Test: `tests/Feature/AdminAssetPagesTest.php`

- [ ] Add forms for create/import/edit/toggle and tables with counts, errors, and empty states.
- [ ] Add safe image previews and explicit alt/版权 fields.
- [ ] Add title bulk textarea with duplicate feedback and source fetch controls.
- [ ] Add navigation badges for pending reviews, failed runs, unprocessed candidates, and source errors.
- [ ] Run view route smoke tests and focused feature tests.

### Task 3: Batch execution and retry controls

**Files:**
- Modify: `app/Http/Controllers/Admin/BatchController.php`, `routes/web.php`
- Modify: `resources/views/admin/batches/index.blade.php`, `show.blade.php`
- Test: `tests/Feature/AdminBatchOperationsTest.php`

- [ ] Add POST action to create a batch for a selected date and queue generation for selected/failed items.
- [ ] Add batch detail aggregates for generation/review/publish states and latest errors.
- [ ] Add idempotent retry actions that only operate on retryable states.
- [ ] Add HTML and JSON responses with job IDs or clear queued status.
- [ ] Test duplicate date behavior, retry guards, and state readback.

### Task 4: Review workflow completeness

**Files:**
- Modify: `app/Http/Controllers/Admin/ReviewController.php`, `resources/views/admin/reviews/index.blade.php`, `show.blade.php`
- Modify: `app/Jobs/PublishWordPressDraftJob.php` only if readback/error payload needs exposure
- Test: `tests/Feature/AdminReviewWorkflowTest.php`

- [ ] Add filters for awaiting, manual, approved, failed, and draft-written content.
- [ ] Add evidence/source/image/alt panels and two-pass review result display.
- [ ] Add regenerate/retry action with state guards and audit event.
- [ ] Add draft publishing form that selects an active WordPress connection and returns readback data.
- [ ] Test approve/manual/retry/write-draft guards and JSON responses.

### Task 5: WordPress connection operations

**Files:**
- Modify: `app/Http/Controllers/Admin/WordPressConnectionController.php`, `routes/web.php`
- Modify: `resources/views/admin/wordpress/index.blade.php`
- Test: `tests/Feature/AdminWordPressConnectionTest.php`

- [ ] Add validated edit form preserving encrypted password when blank.
- [ ] Add REST health test using `WordPressRestClient`, storing sanitized status/error and timestamp.
- [ ] Add draft list/readback endpoint using idempotency marker and external post link.
- [ ] Distinguish URL, authentication, permission, and invalid JSON errors in UI.
- [ ] Test with `Http::fake()` for success, 401, and malformed JSON responses.

### Task 6: Verification and handoff

**Files:**
- Modify: `README.md`, `PROJECT_HANDOFF.md`

- [ ] Run migrations against the project SQLite database.
- [ ] Run all Feature tests and `php artisan route:list`.
- [ ] Smoke test `/admin`, `/admin/knowledge`, `/admin/images`, `/admin/titles`, `/admin/sources`, `/admin/batches`, `/admin/reviews`, `/admin/wordpress` with JSON headers.
- [ ] Confirm no secret values appear in JSON or rendered HTML.
- [ ] Update handoff documentation with routes, operations, and remaining external prerequisites.
