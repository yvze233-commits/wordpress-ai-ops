@extends('admin.layout', ['title' => '热点来源'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Asset library</div><h1>热点来源</h1><p class="subtitle">RSS / JSON / HTML 来源的抓取配置；抓取到的内容与标题库一起参与每日选题。</p></div>
        <a class="button ghost" href="{{ route('admin.batches.index') }}">内容批次</a>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <style>
        .status-cell { display: grid; gap: 4px; justify-items: start; }
        .source-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .test-result { display: none; margin-top: 10px; padding: 10px 12px; border-radius: 6px; font-size: 12.5px; border: 1px solid transparent; }
        .test-result.show { display: block; }
        .test-result.ok { color: var(--teal-dark); background: #eaf8f4; border-color: #bfe8de; }
        .test-result.fail { color: #a64641; background: #fbe8e7; border-color: #f0cdc9; }
        .parse-hint { font-size: 11.5px; color: var(--muted); line-height: 1.7; }
        .parse-hint code { padding: 1px 5px; border-radius: 4px; background: #eef3f2; font-size: 11px; }
    </style>

    <div class="workspace-grid">
        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>来源列表</h2><p>「测试」不写入数据；「立即抓取」会真实抓取并更新候选。</p></div></div>
                <div class="panel-body">
                    @if ($sources->isEmpty())
                        <div class="empty"><strong>还没有来源</strong>从右侧添加 RSS、JSON 或 HTML 来源。</div>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>来源</th><th>类型</th><th>状态</th><th>内容数</th><th>最近抓取</th><th>最近结果</th><th>操作</th></tr></thead>
                                <tbody>
                                @foreach ($sources as $source)
                                    <tr data-source-row="{{ $source->id }}">
                                        <td style="max-width:220px;white-space:normal"><strong>{{ $source->name }}</strong><br><small style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($source->url, 46) }}</small></td>
                                        <td><span class="badge blue">{{ strtoupper($source->type) }}</span></td>
                                        <td class="status-cell">
                                            <span class="badge {{ $source->enabled ? 'teal' : 'gray' }}">{{ $source->enabled ? '启用' : '停用' }}</span>
                                            @if ($source->status === 'paused')<span class="badge amber">已暂停</span>@endif
                                        </td>
                                        <td>{{ $source->feeds_count }}</td>
                                        <td>{{ $source->last_fetched_at?->format('m-d H:i') ?? '从未' }}</td>
                                        <td style="max-width:180px;white-space:normal">
                                            @if ($source->last_error)
                                                <span class="badge red">失败{{ $source->last_http_status ? " · HTTP {$source->last_http_status}" : '' }}</span>
                                                <small style="color:#a64641;display:block;margin-top:3px">{{ \Illuminate\Support\Str::limit($source->last_error, 60) }}</small>
                                            @elseif ($source->last_fetched_at)
                                                <span class="badge teal">正常{{ $source->last_http_status ? " · {$source->last_http_status}" : '' }}</span>
                                            @else
                                                <span class="badge gray">未抓取</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="source-actions">
                                                <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="button" data-action="test-source" data-source="{{ $source->id }}">测试</button>
                                                <form method="post" action="{{ route('admin.sources.collect', $source) }}">@csrf
                                                    <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">立即抓取</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.sources.toggle', $source) }}">@csrf
                                                    <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">{{ $source->enabled ? '停用' : '启用' }}</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.sources.pause', $source) }}">@csrf
                                                    <button class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">{{ $source->status === 'paused' ? '恢复' : '暂停' }}</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.sources.delete', $source) }}" onsubmit="return confirm('确认删除来源「{{ $source->name }}」及其抓取记录？')">@csrf
                                                    <button class="button warn" style="min-height:30px;padding:0 10px;font-size:12px" type="submit">删除</button>
                                                </form>
                                            </div>
                                            <div class="test-result" data-role="source-result-{{ $source->id }}"></div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="panel">
                <div class="panel-header"><div><h2>添加来源</h2><p>保存后建议先「测试」确认能解析到标题。</p></div></div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.sources.store') }}">
                        @csrf
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>名称</span><input name="name" value="{{ old('name') }}" placeholder="如：行业新闻 RSS" required></label>
                            <label><span>类型</span>
                                <select name="type">
                                    <option value="rss">RSS / Atom</option>
                                    <option value="json">JSON API</option>
                                    <option value="html">HTML 页面</option>
                                </select>
                            </label>
                            <label><span>来源地址</span><input name="url" value="{{ old('url') }}" placeholder="https://example.com/feed" required></label>
                            <label><span>信任分</span><input type="number" name="trust_score" value="{{ old('trust_score', 50) }}" min="0" max="100"><small>影响选题优先级（0-100）。</small></label>
                            <label><span>解析配置（JSON，可选）</span><textarea name="parser_config" rows="4" placeholder='{"items_path":"data.list","title_path":"title"}'></textarea>
                                <span class="parse-hint">
                                    JSON 来源：<code>items_path</code> 列表路径、<code>title_path</code>、<code>url_path</code>；<br>
                                    HTML 来源（必填两项）：<code>item_selector</code>、<code>title_selector</code>、可选 <code>url_selector</code>、<code>summary_selector</code>。
                                </span>
                            </label>
                        </div>
                        <div style="margin-top:14px"><button class="button" type="submit">创建来源</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('click', async function (event) {
            var button = event.target.closest('button[data-action="test-source"]');
            if (!button) return;
            var sourceId = button.dataset.source;
            var box = document.querySelector('[data-role="source-result-' + sourceId + '"]');
            button.disabled = true;
            box.className = 'test-result show ok';
            box.textContent = '正在测试来源…';
            try {
                var response = await fetch('{{ url('admin/sources') }}/' + sourceId + '/test', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var payload = await response.json();
                var data = payload.data || payload;
                box.className = 'test-result show ' + (data.ok ? 'ok' : 'fail');
                box.textContent = data.message + (data.samples && data.samples.length ? ' 示例：' + data.samples.slice(0, 3).join(' / ') : '');
            } catch (error) {
                box.className = 'test-result show fail';
                box.textContent = '请求失败：' + error.message;
            } finally {
                button.disabled = false;
            }
        });
    </script>
@endsection
