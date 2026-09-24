@extends('admin.layout', ['title' => '批次详情'])

@php
    $stateLabels = [
        'candidate' => '候选', 'locked' => '已锁定', 'generating' => '生成中', 'awaiting_review' => '待审核',
        'needs_manual_review' => '人工复核', 'approved' => '已批准', 'retryable_failed' => '失败可重试',
        'permanently_failed' => '已失败', 'wp_draft_written' => '草稿已写入', 'published' => '已发布',
    ];
    $stateColors = [
        'candidate' => 'gray', 'locked' => 'blue', 'generating' => 'amber', 'awaiting_review' => 'amber',
        'needs_manual_review' => 'red', 'approved' => 'teal', 'retryable_failed' => 'red',
        'permanently_failed' => 'gray', 'wp_draft_written' => 'teal', 'published' => 'teal',
    ];
@endphp

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow"><a href="{{ route('admin.batches.index') }}" style="color:inherit">内容批次</a> / 详情</div><h1>{{ $batch->run_date->toDateString() }} 批次</h1>
            <p class="subtitle">{{ ['completed' => '选题已完成', 'shortfall' => '选题数量不足', 'planned' => '创建中'][$batch->status] ?? $batch->status }} · 选中 {{ $batch->completed_count }}/{{ $batch->target_count }} 篇</p></div>
        <div class="actions">
            <span class="badge {{ $batch->status === 'completed' ? 'teal' : ($batch->status === 'shortfall' ? 'amber' : 'gray') }}">{{ ['completed' => '已完成', 'shortfall' => '数量不足', 'planned' => '创建中'][$batch->status] ?? $batch->status }}</span>
            <a class="button ghost" href="{{ route('admin.reviews.index') }}">审核队列</a>
        </div>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif

    <section class="panel">
        <div class="panel-header"><div><h2>批次文章</h2><p>按状态对每篇文章执行生成、审核、取消等操作；点击标题进入生产日志。</p></div></div>
        <div class="panel-body">
            @if ($batch->contentItems->isEmpty())
                <div class="empty"><strong>本批次还没有文章</strong>请检查标题库或热点来源是否有可用选题。</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>标题</th><th>状态</th><th>操作</th></tr></thead>
                        <tbody>
                        @foreach ($batch->contentItems as $item)
                            <tr>
                                <td style="max-width:380px;white-space:normal"><a href="{{ route('admin.reviews.show', $item) }}" style="color:var(--teal-dark);font-weight:650">{{ $item->title }}</a></td>
                                <td><span class="badge {{ $stateColors[$item->state] ?? 'gray' }}">{{ $stateLabels[$item->state] ?? $item->state }}</span></td>
                                <td>
                                    <div class="actions">
                                        @if (in_array($item->state, ['locked', 'retryable_failed'], true))
                                            <form method="post" action="{{ route('admin.items.generate', $item) }}">@csrf
                                                <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">{{ $item->state === 'retryable_failed' ? '重试生成' : '生成' }}</button>
                                            </form>
                                        @endif
                                        @if ($item->state === 'awaiting_review')
                                            <form method="post" action="{{ route('admin.items.review', $item) }}">@csrf
                                                <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">AI 审核</button>
                                            </form>
                                        @endif
                                        @if (in_array($item->state, ['awaiting_review', 'needs_manual_review', 'approved'], true))
                                            <a class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" href="{{ route('admin.reviews.show', $item) }}">审核页</a>
                                        @endif
                                        @if (in_array($item->state, ['candidate', 'locked'], true))
                                            <form method="post" action="{{ route('admin.items.cancel', $item) }}" onsubmit="return confirm('确认取消这篇选题？取消后不会再生成。')">@csrf
                                                <button class="button warn" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">取消</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
