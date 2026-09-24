@extends('admin.layout', ['title' => '文章详情'])

@php
    $isManual = $item->state === 'needs_manual_review';
    $review = $item->review_result ?? [];
    $isReviewable = in_array($item->state, ['awaiting_review', 'needs_manual_review'], true);
    $stateLabels = [
        'candidate' => '候选', 'locked' => '已锁定', 'generating' => '生成中', 'awaiting_review' => '待审核',
        'needs_manual_review' => '人工复核', 'approved' => '已批准', 'retryable_failed' => '失败可重试',
        'permanently_failed' => '已失败', 'wp_draft_written' => '草稿已写入', 'published' => '已发布',
    ];
    $stageLabels = ['generation' => '生成', 'review' => '审核', 'wordpress_publish' => '发布'];
    $eventLabels = [
        'generation_queued' => '进入生成队列', 'content_generation_started' => '开始生成',
        'content_generated' => '生成完成', 'content_generation_failed' => '生成失败',
        'content_review_started' => '开始审核', 'content_review_passed' => '审核通过',
        'content_review_manual' => '转人工复核', 'content_review_failed' => '审核失败',
        'content_edited' => '人工编辑', 'content_cancelled' => '已取消',
        'wp_draft_written' => '草稿已写入', 'wordpress_draft_written' => '草稿已写入', 'wordpress_draft_failed' => '草稿写入失败',
    ];
    $runStatusColors = ['succeeded' => 'teal', 'running' => 'amber', 'failed' => 'red'];
    $markdown = (string) ($item->generation_meta['content_markdown'] ?? '');
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow"><a href="{{ route('admin.reviews.index') }}" style="color:inherit">审核队列</a> / 文章生产日志</div><h1>审核文章</h1><p class="subtitle">查看文章正文、生产日志、配图位置，并可直接编辑后重新审核。</p></div>
        <div class="actions">
            <a class="button ghost" href="{{ $item->content_batch_id ? route('admin.batches.show', $item->content_batch_id) : route('admin.dashboard') }}">← 返回批次</a>
            <span class="badge {{ $isManual ? 'red' : ($isReviewable ? 'amber' : 'teal') }}">{{ $stateLabels[$item->state] ?? $item->state }}</span>
        </div>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif
    @if ($isManual)<div class="notice">这篇文章被 AI 审核标记为需要人工确认。请检查事实依据、敏感表达和配图后再决定是否批准。</div>@endif

    <style>
        .log-line { display: flex; gap: 11px; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 12.5px; }
        .log-line:last-child { border-bottom: 0; }
        .log-time { flex: 0 0 96px; color: var(--muted); font-size: 11.5px; padding-top: 2px; }
        .placement-row { display: flex; gap: 11px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 12.5px; }
        .placement-row:last-child { border-bottom: 0; }
        .placement-row img { width: 72px; height: 50px; object-fit: cover; border-radius: 5px; background: #eef2f1; }
    </style>

    <div class="workspace-grid">
        <div class="stack">
            <article class="panel article" style="max-width:none">
                <h1>{{ $item->title }}</h1>
                @if ($item->excerpt)<p class="subtitle">{{ $item->excerpt }}</p>@endif
                <hr style="border:0;border-top:1px solid var(--line);margin:22px 0">
                {!! $item->content_html !!}
            </article>

            @if ($isReviewable)
                <section class="panel" id="edit-panel">
                    <div class="panel-header"><div><h2>编辑文章</h2><p>修改标题和 Markdown 正文；保存后可自动重新配图并提交 AI 审核。</p></div></div>
                    <div class="panel-body">
                        <form method="post" action="{{ route('admin.reviews.update', $item) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr">
                                <label><span>标题</span><input name="title" value="{{ old('title', $item->title) }}" required></label>
                                <label><span>摘要</span><input name="excerpt" value="{{ old('excerpt', $item->excerpt) }}"></label>
                                <label><span>Markdown 正文</span><textarea name="content_markdown" rows="16" placeholder="{{ $markdown === '' ? '当前文章没有 Markdown 原稿，请粘贴完整 Markdown 内容。' : '' }}">{{ old('content_markdown', $markdown) }}</textarea><small>保存后会按新内容重新匹配配图。</small></label>
                                <label class="check-field" style="padding-top:0"><input type="checkbox" name="rerun_review" value="1" @checked(old('rerun_review'))><span><strong>保存后重新提交 AI 审核</strong><small>人工复核中的文章会先转回待审核。</small></span></label>
                            </div>
                            <div style="margin-top:14px"><button class="button" type="submit">保存编辑</button></div>
                        </form>
                    </div>
                </section>
            @endif
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>处理操作</h2><p>操作会写入内容状态记录</p></div></div>
                <div class="panel-body actions">
                    @if ($isReviewable)
                        <form method="post" action="{{ route('admin.reviews.approve', $item) }}">@csrf<button class="button" type="submit">批准文章</button></form>
                        <form method="post" action="{{ route('admin.reviews.rerun-review', $item) }}">@csrf<button class="button ghost" type="submit">重新 AI 审核</button></form>
                        @if (!$isManual)<form method="post" action="{{ route('admin.reviews.manual', $item) }}">@csrf<button class="button warn" type="submit">转人工复核</button></form>@endif
                    @elseif ($item->state === 'approved')
                        <form method="post" action="{{ route('admin.reviews.write-draft', $item) }}">@csrf<button class="button" type="submit">写入 WordPress 草稿</button></form>
                    @else
                        <span class="badge teal">当前状态：{{ $stateLabels[$item->state] ?? $item->state }}</span>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>生产日志</h2><p>生成、审核、发布的每次执行记录。</p></div></div>
                <div class="panel-body">
                    @if ($item->runs->isEmpty() && $item->auditEvents->isEmpty())
                        <div class="empty"><strong>暂无执行记录</strong></div>
                    @else
                        @if ($item->runs->isNotEmpty())
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>阶段</th><th>次数</th><th>结果</th><th>说明</th><th>时间</th></tr></thead>
                                    <tbody>
                                    @foreach ($item->runs as $run)
                                        <tr>
                                            <td>{{ $stageLabels[$run->stage] ?? $run->stage }}</td>
                                            <td>#{{ $run->attempt }}</td>
                                            <td><span class="badge {{ $runStatusColors[$run->status] ?? 'gray' }}">{{ ['succeeded' => '成功', 'running' => '进行中', 'failed' => '失败'][$run->status] ?? $run->status }}</span></td>
                                            <td style="max-width:190px;white-space:normal">{{ $run->error_message ?? (($run->payload['score'] ?? null) !== null ? '得分 '.$run->payload['score'] : '—') }}</td>
                                            <td>{{ $run->created_at?->format('m-d H:i:s') }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        <div style="margin-top:14px">
                            @foreach ($item->auditEvents as $event)
                                <div class="log-line">
                                    <span class="log-time">{{ $event->created_at?->format('m-d H:i:s') }}</span>
                                    <span><strong>{{ $eventLabels[$event->event_type] ?? $event->event_type }}</strong>
                                        @if (filled($event->payload['exception'] ?? null))<small style="color:#a64641"> · {{ $event->payload['exception'] }}</small>@endif
                                        @if (filled($event->payload['score'] ?? null))<small> · 得分 {{ $event->payload['score'] }}</small>@endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>配图位置</h2><p>图片按备注和关键词语义匹配插入。</p></div><span class="badge blue">{{ $item->imagePlacements->count() }} 张</span></div>
                <div class="panel-body">
                    @if ($item->imagePlacements->isEmpty())
                        <div class="empty"><strong>没有匹配到配图</strong></div>
                    @else
                        @foreach ($item->imagePlacements->sortBy('position') as $placement)
                            <div class="placement-row">
                                @if ($placement->image)<img src="{{ route('admin.images.file', $placement->image) }}" alt="{{ $placement->image->alt_text ?? '' }}">@endif
                                <div>
                                    <strong>{{ $placement->role === 'featured' ? '特色图' : '正文配图' }}{{ $placement->paragraph_anchor ? ' · '.$placement->paragraph_anchor : '' }} <small>匹配度 {{ round((float) $placement->confidence * 100, 1) }}%</small></strong>
                                    <small style="color:var(--muted);display:block">{{ $placement->image?->caption }}{{ $placement->reason ? ' · '.$placement->reason : '' }}</small>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>生成上下文</h2><p>本次文章使用的配置</p></div></div>
                <div class="panel-body">
                    <div class="asset-list">
                        <div class="asset"><span class="asset-mark">✦</span><span class="asset-copy"><strong>写作 Skill</strong><small>{{ $item->writing_skill_snapshot['name'] ?? '系统默认' }}</small></span></div>
                        <div class="asset"><span class="asset-mark">✓</span><span class="asset-copy"><strong>审核 Skill</strong><small>{{ $item->review_skill_snapshot['name'] ?? '系统默认' }}</small></span></div>
                        @if ($item->scheduled_publish_at)<div class="asset"><span class="asset-mark">⏱</span><span class="asset-copy"><strong>定时发布</strong><small>{{ $item->scheduled_publish_at->format('m-d H:i') }} · 以 {{ $item->publish_status === 'publish' ? '正式发布' : '草稿' }} 状态推送</small></span></div>@endif
                        @if ($item->wordpress_post_id)<div class="asset"><span class="asset-mark">↗</span><span class="asset-copy"><strong>WordPress</strong><small>草稿 #{{ $item->wordpress_post_id }}{{ $item->wordpress_url ? ' · '.$item->wordpress_url : '' }}</small></span></div>@endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
