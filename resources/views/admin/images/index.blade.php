@extends('admin.layout', ['title' => '图片库'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Asset library</div><h1>图片库</h1><p class="subtitle">管理文章配图素材：上传、备注、关键词和版权状态，供 AI 按语义匹配插图。</p></div>
        <a class="button ghost" href="{{ route('admin.knowledge.index') }}">知识库</a>
    </div>
    @if (session('status'))<div class="notice" style="border-left-color:var(--teal);color:var(--teal-dark);background:#eaf8f4">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="notice" style="border-left-color:var(--red);color:#a64641;background:#fbe8e7">{{ session('error') }}</div>@endif

    <div class="workspace-grid">
        <section class="panel">
            <div class="panel-header"><div><h2>图片库列表</h2><p>点击查看进入图片管理和配图匹配测试。</p></div></div>
            <div class="panel-body">
                @if ($libraries->isEmpty())
                    <div class="empty"><strong>还没有图片库</strong>先在右侧创建一个图片库，再上传图片。</div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>名称</th><th>说明</th><th>状态</th><th>图片数</th><th>操作</th></tr></thead>
                            <tbody>
                            @foreach ($libraries as $library)
                                <tr>
                                    <td><a href="{{ route('admin.images.show', $library) }}" style="color:var(--teal-dark);font-weight:650">{{ $library->name }}</a></td>
                                    <td>{{ \Illuminate\Support\Str::limit((string) $library->description, 40) }}</td>
                                    <td><span class="badge {{ $library->enabled ? 'teal' : 'gray' }}">{{ $library->enabled ? '启用' : '停用' }}</span></td>
                                    <td>{{ $library->images_count }}</td>
                                    <td><a class="button ghost" style="min-height:30px;padding:0 10px;font-size:12px" href="{{ route('admin.images.show', $library) }}">查看</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><div><h2>新建图片库</h2><p>创建后即可上传图片。</p></div></div>
            <div class="panel-body">
                <form method="post" action="{{ route('admin.images.store') }}">
                    @csrf
                    <div class="form-grid" style="grid-template-columns:1fr">
                        <label><span>名称</span><input name="name" value="{{ old('name') }}" placeholder="如：教育产品配图" required></label>
                        <label><span>说明</span><textarea name="description" rows="3" placeholder="这个图片库收集什么图">{{ old('description') }}</textarea></label>
                    </div>
                    <div style="margin-top:14px"><button class="button" type="submit">创建图片库</button></div>
                </form>
            </div>
        </section>
    </div>
@endsection
