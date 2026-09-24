@extends('admin.layout', ['title' => '批次'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Workspace</div><h1>内容批次</h1><p class="subtitle">每日批次从标题库和热点来源中选题，生成后进入审核队列。</p></div>
        <form method="post" action="{{ route('admin.batches.create-daily') }}">@csrf
            <button class="button" type="submit" onclick="this.textContent='正在创建…'">创建今日批次 <span aria-hidden="true">→</span></button>
        </form>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif

    <section class="panel">
        <div class="panel-header"><div><h2>批次列表</h2><p>状态 planned=创建中、completed=选题已满、shortfall=数量不足。</p></div></div>
        <div class="panel-body">
            @if ($batches->isEmpty())
                <div class="empty"><strong>还没有批次</strong>点击右上角「创建今日批次」开始第一次生产。</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>批次日期</th><th>状态</th><th>选题进度</th><th>操作</th></tr></thead>
                        <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td><a href="{{ route('admin.batches.show', $batch) }}" style="color:var(--teal-dark);font-weight:650">{{ $batch->run_date->toDateString() }}</a></td>
                                <td><span class="badge {{ $batch->status === 'completed' ? 'teal' : ($batch->status === 'shortfall' ? 'amber' : 'gray') }}">{{ ['completed' => '已完成', 'shortfall' => '数量不足', 'planned' => '创建中'][$batch->status] ?? $batch->status }}</span></td>
                                <td>{{ $batch->completed_count }}/{{ $batch->target_count }}</td>
                                <td><a class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" href="{{ route('admin.batches.show', $batch) }}">查看</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination">{{ $batches->links() }}</div>
            @endif
        </div>
    </section>
@endsection
