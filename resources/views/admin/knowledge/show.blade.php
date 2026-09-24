@extends('admin.layout', ['title' => '知识库 · '.$base->name])

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.knowledge.index') }}" style="color:inherit">知识库</a> / 详情</div>
            <h1>{{ $base->name }} <span class="badge {{ $base->enabled ? 'teal' : 'gray' }}" style="vertical-align:middle">{{ $base->enabled ? '启用中' : '已停用' }}</span></h1>
            <p class="subtitle">{{ $base->description ?: 'AI 生成文章时会从这个知识库检索资料。' }}</p>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('admin.knowledge.toggle', $base) }}">@csrf
                <button class="button ghost" type="submit">{{ $base->enabled ? '停用知识库' : '启用知识库' }}</button>
            </form>
            <form method="post" action="{{ route('admin.knowledge.destroy', $base) }}" onsubmit="return confirm('确认删除知识库「{{ $base->name }}」及其全部文档？此操作不可恢复。')">@csrf
                <button class="button warn" type="submit">删除知识库</button>
            </form>
        </div>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <div class="stat-grid">
        <div class="stat"><div class="stat-top">文档<span class="stat-icon">≡</span></div><strong>{{ $base->documents->count() }}</strong><small>个文档</small></div>
        <div class="stat"><div class="stat-top">切片<span class="stat-icon">▤</span></div><strong>{{ $base->documents->sum('chunks_count') }}</strong><small>个检索切片</small></div>
        <div class="stat"><div class="stat-top">已审核<span class="stat-icon">✓</span></div><strong>{{ $base->documents->where('review_status', 'reviewed')->count() }}</strong><small>检索时优先</small></div>
        <div class="stat"><div class="stat-top">总字数<span class="stat-icon">字</span></div><strong>{{ number_format((int) $base->documents->sum('content_length')) }}</strong><small>字符</small></div>
    </div>

    <style>
        .doc-review-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .doc-review-form select { min-height: 30px; width: auto; padding: 0 8px; font-size: 12px; }
        .retrieval-result { margin-top: 12px; display: grid; gap: 9px; }
        .retrieval-item { padding: 11px 13px; border: 1px solid var(--line); border-radius: 7px; background: #fbfcfc; font-size: 12.5px; }
        .retrieval-item strong { display: block; margin-bottom: 3px; }
        .retrieval-item small { color: var(--muted); }
    </style>

    <div class="workspace-grid">
        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>文档列表</h2><p>待审核文档低优先参与检索；「排除」完全不参与；高风险未审核文档会被过滤。</p></div></div>
                <div class="panel-body">
                    @if ($base->documents->isEmpty())
                        <div class="empty"><strong>还没有文档</strong>从右侧上传文件或粘贴文本。</div>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>文档</th><th>类型</th><th>审核状态 / 风险</th><th>切片</th><th>字数</th><th>上传时间</th><th>操作</th></tr></thead>
                                <tbody>
                                @foreach ($base->documents as $document)
                                    <tr>
                                        <td style="max-width:220px;white-space:normal"><strong>{{ $document->name }}</strong>@if($document->source_url)<br><small style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($document->source_url, 42) }}</small>@endif</td>
                                        <td><span class="badge gray">{{ ['text' => '文本', 'markdown' => 'MD', 'word' => 'DOCX', 'txt' => 'TXT', 'md' => 'MD'][$document->source_type] ?? $document->source_type }}</span></td>
                                        <td>
                                            <form class="doc-review-form" method="post" action="{{ route('admin.knowledge.documents.update', [$base, $document]) }}">@csrf
                                                <select name="review_status">
                                                    @foreach ($reviewStatuses as $status)
                                                        <option value="{{ $status }}" @selected($document->review_status === $status)>{{ ['pending' => '待审核', 'reviewed' => '已审核', 'excluded' => '排除'][$status] }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="risk_level">
                                                    @foreach ($riskLevels as $level)
                                                        <option value="{{ $level }}" @selected($document->risk_level === $level)>{{ ['low' => '低风险', 'medium' => '中风险', 'high' => '高风险'][$level] }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">保存</button>
                                            </form>
                                        </td>
                                        <td>{{ $document->chunks_count }}</td>
                                        <td>{{ number_format($document->content_length) }}</td>
                                        <td>{{ $document->created_at->format('m-d H:i') }}</td>
                                        <td>
                                            <div style="display:flex;gap:6px">
                                                <a class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" href="{{ route('admin.knowledge.documents.chunks', [$base, $document]) }}">切片</a>
                                                <form method="post" action="{{ route('admin.knowledge.documents.destroy', [$base, $document]) }}" onsubmit="return confirm('确认删除文档「{{ $document->name }}」？')">@csrf
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
                <div class="panel-header"><div><h2>检索测试</h2><p>模拟文章生成时的知识检索，查看会命中哪些切片。</p></div><span class="badge blue">不消耗 AI 调用</span></div>
                <div class="panel-body">
                    <div class="form-grid" style="grid-template-columns:1fr auto;align-items:end">
                        <label><span>测试问题</span><input id="retrieval-query" placeholder="例如：平台的核心功能是什么"></label>
                        <button class="button" type="button" id="retrieval-run">运行检索测试</button>
                    </div>
                    <div class="retrieval-result" id="retrieval-result"></div>
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>上传文档</h2><p>支持 TXT / Markdown / DOCX，单文件 8MB，一次最多 10 个。</p></div></div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.knowledge.documents.upload', $base) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>选择文件</span><input type="file" name="documents[]" multiple accept=".txt,.md,.markdown,.docx"><small>内容相同的文档会自动去重。</small></label>
                            <label><span>或粘贴文本</span><textarea name="pasted_text" rows="5" placeholder="把要入库的资料直接粘贴到这里"></textarea></label>
                            <label><span>文本名称</span><input name="pasted_name" placeholder="可选，默认按时间命名"></label>
                            <label><span>审核状态</span>
                                <select name="review_status">
                                    @foreach ($reviewStatuses as $status)
                                        <option value="{{ $status }}" @selected($status === 'pending')>{{ ['pending' => '待审核', 'reviewed' => '已审核', 'excluded' => '排除'][$status] }}</option>
                                    @endforeach
                                </select><small>已审核的资料检索时优先。</small>
                            </label>
                            <label><span>风险等级</span>
                                <select name="risk_level">
                                    @foreach ($riskLevels as $level)
                                        <option value="{{ $level }}" @selected($level === 'low')>{{ ['low' => '低风险', 'medium' => '中风险', 'high' => '高风险'][$level] }}</option>
                                    @endforeach
                                </select><small>高风险且未审核的内容不会被引用。</small>
                            </label>
                            <label><span>来源链接</span><input type="url" name="source_url" placeholder="可选"></label>
                        </div>
                        <div style="margin-top:14px"><button class="button" type="submit">导入文档</button></div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>基本信息</h2><p>修改名称或说明。</p></div></div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.knowledge.update', $base) }}">
                        @csrf
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>名称</span><input name="name" value="{{ old('name', $base->name) }}" required></label>
                            <label><span>说明</span><textarea name="description" rows="3">{{ old('description', $base->description) }}</textarea></label>
                        </div>
                        <div style="margin-top:14px"><button class="button ghost" type="submit">保存修改</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.getElementById('retrieval-run').addEventListener('click', async function () {
            var button = this;
            var box = document.getElementById('retrieval-result');
            var query = document.getElementById('retrieval-query').value.trim();
            if (!query) { box.innerHTML = '<div class="retrieval-item">请先输入测试问题。</div>'; return; }
            button.disabled = true;
            box.innerHTML = '<div class="retrieval-item">正在检索…</div>';
            try {
                var body = new URLSearchParams();
                body.set('_token', '{{ csrf_token() }}');
                body.set('query', query);
                var response = await fetch('{{ route('admin.knowledge.test-retrieval', $base) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                });
                var payload = await response.json();
                var data = payload.data || payload;
                if (!data.evidence_count) {
                    box.innerHTML = '<div class="retrieval-item"><strong>没有命中任何切片</strong><small>换一个更接近资料内容的问题试试。</small></div>';
                    return;
                }
                box.innerHTML = '<div class="retrieval-item"><strong>命中 '+data.evidence_count+' 个切片</strong></div>' + data.evidence.map(function (item) {
                    return '<div class="retrieval-item"><strong>'+(item.heading || item.source_name || '未命名')+' <small>得分 '+item.score+'</small></strong><small>'+item.preview.replace(/</g, '&lt;')+'</small></div>';
                }).join('');
            } catch (error) {
                box.innerHTML = '<div class="retrieval-item">检索失败：'+error.message+'</div>';
            } finally {
                button.disabled = false;
            }
        });
    </script>
@endsection
