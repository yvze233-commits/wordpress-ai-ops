@extends('admin.layout', ['title' => '运营概览'])

@php
    $target = max((int) ($data['daily_target'] ?? 0), 1);
    $completed = (int) ($data['selected'] ?? 0);
    $completion = min(100, (int) round(($completed / $target) * 100));
    $stateLabels = [
        'candidate' => '待选题',
        'locked' => '已锁定',
        'generating' => '生成中',
        'awaiting_review' => '待审核',
        'needs_manual_review' => '人工复核',
        'approved' => '已通过',
        'retryable_failed' => '待重试',
        'permanently_failed' => '失败',
        'wp_draft_written' => '已写入草稿',
        'published' => '已发布',
    ];
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Content operations</div>
            <h1>运营概览</h1>
            <p class="subtitle">查看今天的内容生产进度，以及需要人工处理的事项。</p>
        </div>
        <div class="actions">
            <a class="button ghost" href="{{ route('admin.knowledge.index') }}">管理资产</a>
            <a class="button" href="{{ route('admin.reviews.index') }}">查看审核队列 <span aria-hidden="true">→</span></a>
        </div>
    </div>

    <section class="stat-grid" aria-label="今日数据">
        <div class="stat">
            <div class="stat-top"><span>今日发布目标</span><span class="stat-icon">◎</span></div>
            <strong>{{ $target }}</strong><small>篇 / 天</small>
            <div class="progress"><span style="width: {{ $completion }}%"></span></div>
            <div class="progress-meta"><span>完成 {{ $completed }} 篇</span><span>{{ $completion }}%</span></div>
        </div>
        <div class="stat">
            <div class="stat-top"><span>待审核文章</span><span class="stat-icon">✓</span></div>
            <strong>{{ ($data['states']['awaiting_review'] ?? 0) + ($data['states']['needs_manual_review'] ?? 0) }}</strong>
            <small>{{ $data['states']['needs_manual_review'] ?? 0 }} 篇需要人工复核</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>已写入草稿</span><span class="stat-icon">↗</span></div>
            <strong>{{ $data['states']['wp_draft_written'] ?? 0 }}</strong>
            <small>等待 WordPress 发布</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>已发布文章</span><span class="stat-icon">◆</span></div>
            <strong>{{ $data['states']['published'] ?? 0 }}</strong>
            <small>已完成内容闭环</small>
        </div>
    </section>

    <div class="workspace-grid">
        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>内容流水线</h2><p>从选题到 WordPress 的自动化路径</p></div><span class="badge teal">自动运行</span></div>
                <div class="panel-body">
                    <div class="pipeline">
                        <div class="pipeline-step"><span class="step-index">01</span><strong>选题池</strong><small>{{ $data['states']['candidate'] ?? 0 }} 个候选</small></div>
                        <div class="pipeline-step"><span class="step-index">02</span><strong>AI 写作</strong><small>{{ $data['states']['generating'] ?? 0 }} 篇生成中</small></div>
                        <div class="pipeline-step"><span class="step-index">03</span><strong>AI 审核</strong><small>{{ $data['states']['awaiting_review'] ?? 0 }} 篇待审核</small></div>
                        <div class="pipeline-step"><span class="step-index">04</span><strong>发布队列</strong><small>{{ $data['states']['wp_draft_written'] ?? 0 }} 篇待发布</small></div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>待处理文章</h2><p>优先处理人工复核项，避免阻塞自动发布</p></div><a class="button ghost" href="{{ route('admin.reviews.index') }}">全部审核</a></div>
                <div class="panel-body">
                    @forelse ($reviewItems as $item)
                        <div class="review-row">
                            <div><a class="review-title" href="{{ route('admin.reviews.show', $item) }}">{{ $item->title }}</a><span class="review-meta">{{ $item->updated_at?->diffForHumans() ?? '刚刚' }} · {{ $item->topicCandidate?->source ?? '运营选题' }}</span></div>
                            @if ($item->state === 'needs_manual_review')<span class="badge red">人工复核</span>@else<span class="badge amber">待审核</span>@endif
                        </div>
                    @empty
                        <div class="empty"><strong>审核队列为空</strong>新文章生成后会自动出现在这里。</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>资产状态</h2><p>生成文章会按需读取这些内容</p></div></div>
                <div class="panel-body asset-list">
                    <a class="asset" href="{{ route('admin.knowledge.index') }}"><span class="asset-mark">▤</span><span class="asset-copy"><strong>知识库</strong><small>{{ $knowledgeCount }} 个库 · {{ $knowledgeDocuments }} 份资料</small></span><span class="asset-count">{{ $knowledgeCount }}</span></a>
                    <a class="asset" href="{{ route('admin.images.index') }}"><span class="asset-mark">▧</span><span class="asset-copy"><strong>图片库</strong><small>{{ $imageLibraryCount }} 个库 · {{ $imageCount }} 张图片</small></span><span class="asset-count">{{ $imageCount }}</span></a>
                    <a class="asset" href="{{ route('admin.skills.index') }}"><span class="asset-mark">✦</span><span class="asset-copy"><strong>Skills</strong><small>{{ $enabledSkillCount }} 个启用 · 写作与审核可选</small></span><span class="asset-count">{{ $skillCount }}</span></a>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>状态分布</h2><p>当前所有内容项</p></div></div>
                <div class="panel-body asset-list">
                    @foreach (['awaiting_review', 'approved', 'published', 'retryable_failed'] as $state)
                        <div class="asset"><span class="asset-mark">{{ $state === 'retryable_failed' ? '!' : '•' }}</span><span class="asset-copy"><strong>{{ $stateLabels[$state] }}</strong><small>{{ $state === 'retryable_failed' ? '系统会自动重试' : '内容状态' }}</small></span><span class="asset-count">{{ $data['states'][$state] ?? 0 }}</span></div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
