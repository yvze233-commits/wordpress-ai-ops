@extends('admin.layout', ['title' => '文章详情'])

@php
    $isManual = $item->state === 'needs_manual_review';
    $review = $item->review_result ?? [];
    $stateLabels = ['awaiting_review' => '待审核', 'needs_manual_review' => '人工复核', 'approved' => '已通过', 'retryable_failed' => '待重试', 'wp_draft_written' => '已写入草稿', 'published' => '已发布'];
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">文章审核</div><h1>审核文章</h1><p class="subtitle">查看文章正文、审核依据和图片插入结果。</p></div>
        <div class="actions"><a class="button ghost" href="{{ route('admin.reviews.index') }}">← 返回队列</a>@if ($isManual)<span class="badge red">需要人工复核</span>@else<span class="badge amber">待审核</span>@endif</div>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if ($isManual)<div class="notice">这篇文章被 AI 审核标记为需要人工确认。请检查事实依据、敏感表达和配图后再决定是否批准。</div>@endif
    <div class="workspace-grid">
        <article class="panel article">
            <h1>{{ $item->title }}</h1>
            @if ($item->excerpt)<p class="subtitle">{{ $item->excerpt }}</p>@endif
            <hr style="border:0;border-top:1px solid var(--line);margin:22px 0">
            {!! $item->content_html !!}
        </article>
        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>审核结果</h2><p>当前 AI 审核快照</p></div><span class="badge {{ $isManual ? 'red' : 'amber' }}">{{ $isManual ? '人工复核' : '待审核' }}</span></div>
                <div class="panel-body">
                    @if ($review)
                        <div class="asset-list">
                            @foreach ($review as $key => $value)
                                <div class="asset"><span class="asset-copy"><strong>{{ is_string($key) ? $key : '审核项' }}</strong><small>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</small></span></div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty"><strong>暂无审核明细</strong>可以直接批准或转人工处理。</div>
                    @endif
                </div>
            </section>
            <section class="panel">
                <div class="panel-header"><div><h2>处理操作</h2><p>操作会写入内容状态记录</p></div></div>
                <div class="panel-body actions">
                    @if ($item->state === 'retryable_failed')
                        <form method="post" action="{{ route('admin.reviews.retry', $item) }}">@csrf<button class="button warn" type="submit">重新生成</button></form>
                    @elseif (in_array($item->state, ['awaiting_review', 'needs_manual_review'], true))
                        <form method="post" action="{{ route('admin.reviews.approve', $item) }}">@csrf<button class="button" type="submit">批准文章</button></form>
                        @if (!$isManual)<form method="post" action="{{ route('admin.reviews.manual', $item) }}">@csrf<button class="button warn" type="submit">转人工复核</button></form>@endif
                    @else
                        <span class="badge teal">当前状态：{{ $stateLabels[$item->state] ?? $item->state }}</span>
                    @endif
                    @if ($item->state === 'approved')
                        @php($connections = \App\Models\WordPressConnection::query()->whereIn('status', ['active', 'healthy'])->orderBy('name')->get())
                        <form method="post" action="{{ route('admin.reviews.write-draft', $item) }}" class="actions">@csrf<select name="connection_id" required><option value="">选择 WordPress 站点</option>@foreach($connections as $connection)<option value="{{ $connection->id }}">{{ $connection->name }}</option>@endforeach</select><button class="button" type="submit">写入 WordPress 草稿</button></form>
                    @endif
                </div>
            </section>
            <section class="panel">
                <div class="panel-header"><div><h2>生成上下文</h2><p>本次文章使用的配置</p></div></div>
                <div class="panel-body">
                    <div class="asset-list">
                        <div class="asset"><span class="asset-mark">✦</span><span class="asset-copy"><strong>写作 Skill</strong><small>{{ $item->writing_skill_snapshot['name'] ?? '系统默认' }}</small></span></div>
                        <div class="asset"><span class="asset-mark">✓</span><span class="asset-copy"><strong>审核 Skill</strong><small>{{ $item->review_skill_snapshot['name'] ?? '系统默认' }}</small></span></div>
                        <div class="asset"><span class="asset-mark">▧</span><span class="asset-copy"><strong>图片插入</strong><small>{{ $item->imagePlacements->count() }} 个位置已匹配</small></span></div>
                    </div>
                </div>
            </section>
            <section class="panel">
                <div class="panel-header"><div><h2>证据与配图</h2><p>发布前确认来源链接、知识库证据和图片 alt。</p></div></div>
                <div class="panel-body">
                    @if (!empty($item->evidence_snapshot['evidence']))
                        <div class="asset-list">@foreach($item->evidence_snapshot['evidence'] as $evidence)<div class="asset"><span class="asset-mark">↗</span><span class="asset-copy"><strong>{{ $evidence['title'] ?? $evidence['heading'] ?? '知识库证据' }}</strong><small>{{ $evidence['source_url'] ?? $evidence['content'] ?? '已记录证据' }}</small></span></div>@endforeach</div>
                    @else
                        <div class="empty"><strong>暂无知识库证据</strong>请检查生成配置或转人工复核。</div>
                    @endif
                    @if($item->imagePlacements->isNotEmpty())<div class="asset-list" style="margin-top:14px">@foreach($item->imagePlacements as $placement)<div class="asset"><span class="asset-mark">▧</span><span class="asset-copy"><strong>{{ $placement->image?->caption ?: '配图' }}</strong><small>alt：{{ $placement->image?->alt_text ?: '未填写' }} · {{ $placement->reason ?: '自动匹配' }}</small></span></div>@endforeach</div>@endif
                    @if($item->topicCandidate?->source_url)<p style="margin-top:14px"><a class="button ghost" href="{{ $item->topicCandidate->source_url }}" target="_blank" rel="noreferrer">打开选题来源 ↗</a></p>@endif
                </div>
            </section>
        </div>
    </div>
@endsection
