@extends('admin.layout', ['title' => '知识库'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Asset library</div><h1>知识库</h1><p class="subtitle">管理 AI 写作引用的资料：上传 TXT / Markdown / DOCX，审核后才会被优先检索。</p></div>
        <a class="button ghost" href="{{ route('admin.settings.index') }}">AI 与检索配置</a>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif

    @php($totalDocuments = $bases->sum('documents_count'))
    @php($totalChars = (int) $bases->sum('documents_sum_content_length'))

    <div class="stat-grid">
        <div class="stat"><div class="stat-top">知识库<span class="stat-icon">▤</span></div><strong>{{ $bases->count() }}</strong><small>个知识库</small></div>
        <div class="stat"><div class="stat-top">文档<span class="stat-icon">≡</span></div><strong>{{ $totalDocuments }}</strong><small>个文档</small></div>
        <div class="stat"><div class="stat-top">总字数<span class="stat-icon">字</span></div><strong>{{ number_format($totalChars) }}</strong><small>字符</small></div>
        <div class="stat"><div class="stat-top">启用中<span class="stat-icon">✓</span></div><strong>{{ $bases->where('enabled', true)->count() }}</strong><small>参与文章检索</small></div>
    </div>

    <div class="workspace-grid">
        <section class="panel">
            <div class="panel-header"><div><h2>知识库列表</h2><p>点击查看进入文档管理、上传和检索测试。</p></div></div>
            <div class="panel-body">
                @if ($bases->isEmpty())
                    <div class="empty"><strong>还没有知识库</strong>先在右侧创建一个知识库，再上传文档。</div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>名称</th><th>说明</th><th>状态</th><th>文档</th><th>字数</th><th>操作</th></tr></thead>
                            <tbody>
                            @foreach ($bases as $base)
                                <tr>
                                    <td><a href="{{ route('admin.knowledge.show', $base) }}" style="color:var(--teal-dark);font-weight:650">{{ $base->name }}</a></td>
                                    <td>{{ \Illuminate\Support\Str::limit((string) $base->description, 40) }}</td>
                                    <td><span class="badge {{ $base->enabled ? 'teal' : 'gray' }}">{{ $base->enabled ? '启用' : '停用' }}</span></td>
                                    <td>{{ $base->documents_count }}</td>
                                    <td>{{ number_format((int) $base->documents_sum_content_length) }}</td>
                                    <td>
                                        <div style="display:flex;gap:6px">
                                            <a class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" href="{{ route('admin.knowledge.show', $base) }}">查看</a>
                                            <form method="post" action="{{ route('admin.knowledge.toggle', $base) }}">@csrf
                                                <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">{{ $base->enabled ? '停用' : '启用' }}</button>
                                            </form>
                                            <form method="post" action="{{ route('admin.knowledge.destroy', $base) }}" onsubmit="return confirm('确认删除知识库「{{ $base->name }}」及其全部文档？此操作不可恢复。')">@csrf
                                                <button class="button warn" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">删除</button>
                                            </form>
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

        <section class="panel">
            <div class="panel-header"><div><h2>新建知识库</h2><p>创建后即可上传文档。</p></div></div>
            <div class="panel-body">
                <form method="post" action="{{ route('admin.knowledge.store') }}">
                    @csrf
                    <div class="form-grid" style="grid-template-columns:1fr">
                        <label><span>名称</span><input name="name" value="{{ old('name') }}" placeholder="如：产品资料" required></label>
                        <label><span>说明</span><textarea name="description" rows="3" placeholder="这个知识库收集什么资料">{{ old('description') }}</textarea></label>
                    </div>
                    <div style="margin-top:14px"><button class="button" type="submit">创建知识库</button></div>
                </form>
            </div>
        </section>
    </div>
@endsection
