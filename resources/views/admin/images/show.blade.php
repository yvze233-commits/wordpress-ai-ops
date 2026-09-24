@extends('admin.layout', ['title' => '图片库 · '.$library->name])

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.images.index') }}" style="color:inherit">图片库</a> / 详情</div>
            <h1>{{ $library->name }} <span class="badge {{ $library->enabled ? 'teal' : 'gray' }}" style="vertical-align:middle">{{ $library->enabled ? '启用中' : '已停用' }}</span></h1>
            <p class="subtitle">{{ $library->description ?: '备注和关键词会被 AI 用于文章配图匹配。' }}</p>
        </div>
        <form method="post" action="{{ route('admin.images.delete', $library) }}" onsubmit="return confirm('确认删除图片库「{{ $library->name }}」及其全部图片？此操作不可恢复。')">@csrf
            <button class="button warn" type="submit">删除图片库</button>
        </form>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ $errors->first() }}</div>@endif

    <style>
        .image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
        .image-card { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: #fbfcfc; }
        .image-card img { display: block; width: 100%; height: 130px; object-fit: cover; background: #eef2f1; }
        .image-card .image-body { padding: 11px 12px; display: grid; gap: 7px; }
        .image-card strong { font-size: 12.5px; line-height: 1.4; word-break: break-all; }
        .image-card small { color: var(--muted); font-size: 11px; }
        .image-card .form-grid { gap: 8px; }
        .image-card input, .image-card select, .image-card textarea { min-height: 30px; padding: 0 8px; font-size: 12px; }
        .image-card textarea { padding: 6px 8px; }
        .badge-row { display: flex; gap: 5px; flex-wrap: wrap; }
        .match-result { margin-top: 12px; display: grid; gap: 9px; }
        .match-item { display: flex; gap: 11px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 7px; background: #fbfcfc; font-size: 12.5px; align-items: center; }
        .match-item img { width: 74px; height: 52px; object-fit: cover; border-radius: 5px; background: #eef2f1; }
        .match-item strong { display: block; margin-bottom: 2px; }
        .match-item small { color: var(--muted); }
    </style>

    <div class="stack">
        <section class="panel">
            <div class="panel-header"><div><h2>图片列表</h2><p>直接在卡片上编辑备注、关键词和版权状态。</p></div><span class="badge blue">{{ $library->images->count() }} 张</span></div>
            <div class="panel-body">
                @if ($library->images->isEmpty())
                    <div class="empty"><strong>还没有图片</strong>从右侧上传 JPG / PNG / WebP / GIF。</div>
                @else
                    <div class="image-grid">
                        @foreach ($library->images as $image)
                            <div class="image-card">
                                <img src="{{ route('admin.images.file', $image) }}" alt="{{ $image->alt_text ?? $image->caption ?? '图片预览' }}" loading="lazy">
                                <div class="image-body">
                                    <strong>{{ $image->caption ?? '(未命名)' }}</strong>
                                    <div class="badge-row">
                                        <span class="badge {{ $image->enabled ? 'teal' : 'gray' }}">{{ $image->enabled ? '启用' : '停用' }}</span>
                                        <span class="badge {{ $image->copyright_state === 'unknown' ? 'amber' : 'blue' }}">{{ $copyrightLabels[$image->copyright_state] ?? $image->copyright_state }}</span>
                                        @if ($image->placements_count > 0)<span class="badge gray">已用 {{ $image->placements_count }} 次</span>@endif
                                        @if ($image->width) <span class="badge gray">{{ $image->width }}×{{ $image->height }}</span>@endif
                                    </div>
                                    <form method="post" action="{{ route('admin.images.images.update', [$library, $image]) }}">@csrf
                                        <div class="form-grid" style="grid-template-columns:1fr">
                                            <input name="caption" value="{{ $image->caption }}" placeholder="图注/名称">
                                            <input name="keywords" value="{{ implode(',', (array) $image->keywords) }}" placeholder="关键词，逗号分隔">
                                            <textarea name="notes" rows="2" placeholder="备注（适用场景、内容描述）">{{ $image->notes }}</textarea>
                                            <input name="alt_text" value="{{ $image->alt_text }}" placeholder="alt 文本">
                                            <input name="scenes" value="{{ implode(',', (array) $image->scenes) }}" placeholder="适用场景，逗号分隔">
                                            <select name="copyright_state">
                                                @foreach ($copyrightStates as $state)
                                                    <option value="{{ $state }}" @selected($image->copyright_state === $state)>{{ $copyrightLabels[$state] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div style="display:flex;gap:6px;margin-top:8px">
                                            <button class="button ghost" style="min-height:28px;padding:0 10px;font-size:12px" type="submit">保存</button>
                                        </div>
                                    </form>
                                    <div style="display:flex;gap:6px">
                                        <form method="post" action="{{ route('admin.images.images.toggle', [$library, $image]) }}">@csrf
                                            <button class="button ghost" style="min-height:28px;padding:0 10px;font-size:12px" type="submit">{{ $image->enabled ? '停用' : '启用' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.images.images.destroy', [$library, $image]) }}" onsubmit="return confirm('确认删除该图片？')">@csrf
                                            <button class="button warn" style="min-height:28px;padding:0 10px;font-size:12px" type="submit">删除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <div class="workspace-grid">
            <section class="panel">
                <div class="panel-header"><div><h2>配图匹配测试</h2><p>模拟一篇文章，看会选中哪些图、放在什么位置。</p></div><span class="badge blue">不写入数据</span></div>
                <div class="panel-body">
                    <form id="match-form">
                        <div class="form-grid" style="grid-template-columns:1fr">
                            <label><span>文章标题</span><input id="match-title" placeholder="如：在线教育的三大趋势"></label>
                            <label><span>文章正文（空行分段）</span><textarea id="match-text" rows="6" placeholder="## 小标题&#10;段落内容…"></textarea></label>
                            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                                <label><span>分类</span><input id="match-category" placeholder="可选"></label>
                                <label><span>场景</span><input id="match-scene" placeholder="可选"></label>
                            </div>
                        </div>
                        <div style="margin-top:12px"><button class="button" type="button" id="match-run">运行匹配测试</button></div>
                    </form>
                    <div class="match-result" id="match-result"></div>
                </div>
            </section>

            <div class="stack">
                <section class="panel">
                    <div class="panel-header"><div><h2>上传图片</h2><p>一次最多 10 张，单张 5MB；填写的备注对所有图片生效。</p></div></div>
                    <div class="panel-body">
                        <form method="post" action="{{ route('admin.images.upload', $library) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr">
                                <label><span>选择图片</span><input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif"></label>
                                <label><span>图注/名称</span><input name="caption" placeholder="可选"></label>
                                <label><span>关键词</span><input name="keywords" placeholder="如：教室,老师,在线学习"></label>
                                <label><span>备注</span><textarea name="notes" rows="2" placeholder="可选"></textarea></label>
                                <label><span>版权状态</span>
                                    <select name="copyright_state">
                                        @foreach ($copyrightStates as $state)
                                            <option value="{{ $state }}" @selected($state === 'unknown')>{{ $copyrightLabels[$state] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div style="margin-top:14px"><button class="button" type="submit">上传图片</button></div>
                        </form>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header"><div><h2>图片库信息</h2><p>修改名称或说明。</p></div></div>
                    <div class="panel-body">
                        <form method="post" action="{{ route('admin.images.update', $library) }}">
                            @csrf
                            <div class="form-grid" style="grid-template-columns:1fr">
                                <label><span>名称</span><input name="name" value="{{ old('name', $library->name) }}" required></label>
                                <label><span>说明</span><textarea name="description" rows="2">{{ old('description', $library->description) }}</textarea></label>
                            </div>
                            <div style="margin-top:14px"><button class="button ghost" type="submit">保存修改</button></div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('match-run').addEventListener('click', async function () {
            var button = this;
            var box = document.getElementById('match-result');
            var title = document.getElementById('match-title').value.trim();
            var text = document.getElementById('match-text').value.trim();
            if (!title || !text) { box.innerHTML = '<div class="match-item">请填写文章标题和正文。</div>'; return; }
            button.disabled = true;
            box.innerHTML = '<div class="match-item">正在匹配…</div>';
            try {
                var body = new URLSearchParams();
                body.set('_token', '{{ csrf_token() }}');
                body.set('title', title);
                body.set('article_text', text);
                body.set('category', document.getElementById('match-category').value);
                body.set('scene', document.getElementById('match-scene').value);
                var response = await fetch('{{ route('admin.images.match-test', $library) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                });
                var payload = await response.json();
                var data = payload.data || payload;
                if (!data.count) {
                    box.innerHTML = '<div class="match-item"><strong>没有匹配到图片</strong><small>试着让标题、正文和图片的关键词更接近，或检查图片是否已启用。</small></div>';
                    return;
                }
                box.innerHTML = '<div class="match-item"><strong>匹配到 '+data.count+' 个位置</strong></div>' + data.placements.map(function (p) {
                    return '<div class="match-item">'+(p.url ? '<img src="'+p.url+'" alt="">' : '')+'<div><strong>'+p.role+(p.paragraph_anchor ? ' · '+p.paragraph_anchor : '')+' <small>匹配度 '+p.confidence+'%</small></strong><small>'+((p.caption || '')+' '+(p.reason || '')).replace(/</g, '&lt;')+'</small></div></div>';
                }).join('');
            } catch (error) {
                box.innerHTML = '<div class="match-item">匹配失败：'+error.message+'</div>';
            } finally {
                button.disabled = false;
            }
        });
    </script>
@endsection
