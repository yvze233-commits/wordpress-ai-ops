@extends('admin.layout', ['title' => '文章详情'])

@php
    $isManual = $item->state === 'needs_manual_review';
    $review = $item->review_result ?? [];
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Review item</div><h1>审核文章</h1><p class="subtitle">查看文章正文、审核依据和图片插入结果。</p></div>
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
                    @if (in_array($item->state, ['awaiting_review', 'needs_manual_review'], true))
                        <form method="post" action="{{ route('admin.reviews.approve', $item) }}">@csrf<button class="button" type="submit">批准文章</button></form>
                        @if (!$isManual)<form method="post" action="{{ route('admin.reviews.manual', $item) }}">@csrf<button class="button warn" type="submit">转人工复核</button></form>@endif
                    @else
                        <span class="badge teal">当前状态：{{ $item->state }}</span>
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
        </div>
    </div>
@endsection
