@extends('admin.layout', ['title' => '标题库'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Asset library</div><h1>标题库</h1><p class="subtitle">手动添加或批量导入标题，启用的标题会进入每日选题去重池。</p></div>
        <a class="button ghost" href="{{ route('admin.batches.index') }}">内容批次</a>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <div class="stat-grid">
        <div class="stat"><div class="stat-top">标题总数<span class="stat-icon">≡</span></div><strong>{{ $stats['total'] }}</strong><small>条</small></div>
        <div class="stat"><div class="stat-top">启用中<span class="stat-icon">✓</span></div><strong>{{ $stats['enabled'] }}</strong><small>参与选题</small></div>
        <div class="stat"><div class="stat-top">已使用<span class="stat-icon">↗</span></div><strong>{{ $stats['used'] }}</strong><small>已生成过文章</small></div>
        <div class="stat"><div class="stat-top">去重方式<span class="stat-icon">⚑</span></div><strong style="font-size:16px;line-height:1.4">标题归一化</strong><small>已用/重复标题不会再次使用</small></div>
    </div>

    <div class="workspace-grid">
        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>标题列表</h2><p>停用或已使用的标题不会进入每日选题。</p></div></div>
                <div class="panel-body">
                    @if ($titles->isEmpty())
                        <div class="empty"><strong>还没有标题</strong>从右侧添加单个标题，或批量导入 CSV / TXT。</div>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>标题</th><th>分类</th><th>优先级</th><th>状态</th><th>使用次数</th><th>最近使用</th><th>操作</th></tr></thead>
                                <tbody>
                                @foreach ($titles as $title)
                                    <tr>
                                        <td style="max-width:340px;white-space:normal">{{ $title->raw_title }}</td>
                                        <td>{{ $title->category ?? '—' }}</td>
                                        <td>{{ $title->priority }}</td>
                                        <td>
                                            <span class="badge {{ $title->enabled ? 'teal' : 'gray' }}">{{ $title->enabled ? '启用' : '停用' }}</span>
                                            @if ($title->use_count > 0)<span class="badge amber">已使用</span>@endif
                                        </td>
                                        <td>{{ $title->use_count }}</td>
                                        <td>{{ $title->last_used_at?->format('m-d H:i') ?? '—' }}</td>
                                        <td>
                                            <div style="display:flex;gap:6px">
                                                <form method="post" action="{{ route('admin.titles.toggle', $title) }}">@csrf
                                                    <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">{{ $title->enabled ? '停用' : '启用' }}</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.titles.delete', $title) }}" onsubmit="return confirm('确认删除该标题？')">@csrf
                                                    <button class="button warn" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">删除</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination">{{ $titles->links() }}</div>
                    @endif
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>批量导入</h2><p>CSV（列：标题,分类,优先级）或 TXT（每行一个标题）。</p></div></div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.titles.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>选择 CSV / TXT 文件</span><input type="file" name="import_file" accept=".csv,.txt"><small>最大 2MB，重复标题自动跳过。</small></label>
                            <label><span>或粘贴标题</span><textarea name="pasted_text" rows="6" placeholder="每行一个标题"></textarea></label>
                        </div>
                        <div style="margin-top:14px"><button class="button" type="submit">开始导入</button></div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>添加单个标题</h2><p>优先级越高越容易被选中（0-100）。</p></div></div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.titles.store') }}">
                        @csrf
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>标题</span><input name="title" value="{{ old('title') }}" required></label>
                            <label><span>分类</span><input name="category" value="{{ old('category') }}" placeholder="可选"></label>
                            <label><span>优先级</span><input type="number" name="priority" value="{{ old('priority', 50) }}" min="0" max="100"></label>
                        </div>
                        <div style="margin-top:14px"><button class="button ghost" type="submit">添加标题</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
