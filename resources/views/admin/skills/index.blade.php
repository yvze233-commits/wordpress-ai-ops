@extends('admin.layout', ['title' => '写作规则'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">写作与审核规则</div><h1>写作规则</h1><p class="subtitle">为写作和审核流程选择系统预设或用户上传的规则。</p></div>
        <span class="badge teal">{{ $skills->where('enabled', true)->count() }} 个启用</span>
    </div>
    <section class="panel">
        <div class="panel-header"><div><h2>规则列表</h2><p>当前版本会随每篇文章保存，方便回溯生成依据。</p></div><span class="badge blue">支持版本管理</span></div>
        <div class="table-wrap">
            <table><thead><tr><th>名称</th><th>用途</th><th>来源</th><th>版本</th><th>状态</th></tr></thead><tbody>
                @forelse ($skills as $skill)
                    <tr><td><strong>{{ $skill->name }}</strong><br><small style="color:var(--muted)">{{ $skill->slug }}</small></td><td>{{ $skill->kind === 'writing' ? '写作' : '审核' }}</td><td>{{ $skill->source === 'user' ? '用户上传' : '系统预设' }}</td><td>第 {{ $skill->current_version }} 版</td><td><span class="badge {{ $skill->enabled ? 'teal' : 'gray' }}">{{ $skill->enabled ? '启用' : '停用' }}</span></td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><strong>还没有写作规则</strong>可以从系统预设开始，或通过接口上传自定义规则。</div></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>
@endsection
