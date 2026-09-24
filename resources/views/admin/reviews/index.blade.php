@extends('admin.layout', ['title' => '文章审核'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Review queue</div><h1>文章审核</h1><p class="subtitle">检查 AI 生成结果，确认内容可以进入 WordPress 发布队列。</p></div>
        <span class="badge amber">{{ $items->total() }} 篇待处理</span>
    </div>
    <section class="panel">
        <div class="panel-header"><div><h2>审核队列</h2><p>AI 审核会先执行，只有异常内容才需要人工介入。</p></div><a class="button ghost" href="{{ route('admin.dashboard') }}">返回概览</a></div>
        <div class="panel-body">
            @forelse ($items as $item)
                <div class="review-row">
                    <div><a class="review-title" href="{{ route('admin.reviews.show', $item) }}">{{ $item->title }}</a><span class="review-meta">更新于 {{ $item->updated_at?->format('Y-m-d H:i') ?? '-' }} · {{ $item->topicCandidate?->source ?? '运营选题' }}</span></div>
                    <div class="actions"><span class="badge {{ $item->state === 'needs_manual_review' ? 'red' : 'amber' }}">{{ $item->state === 'needs_manual_review' ? '人工复核' : '待审核' }}</span><a class="button ghost" href="{{ route('admin.reviews.show', $item) }}">查看</a></div>
                </div>
            @empty
                <div class="empty"><strong>当前没有待审核文章</strong>生成任务完成后，文章会自动进入这个队列。</div>
            @endforelse
            <div class="pagination">{{ $items->links() }}</div>
        </div>
    </section>
@endsection
