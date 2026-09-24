@extends('admin.layout', ['title' => 'Skills'])

@section('content')
    <div class="page-heading">
        <div><div class="eyebrow">Prompt assets</div><h1>Skills</h1><p class="subtitle">为写作和审核流程选择系统预设或用户上传的 Skill。</p></div>
        <span class="badge teal">{{ $skills->where('enabled', true)->count() }} 个启用</span>
    </div>
    <section class="panel">
        <div class="panel-header"><div><h2>Skill 列表</h2><p>当前版本会随每篇文章保存，方便回溯生成依据。</p></div><span class="badge blue">支持版本管理</span></div>
        <div class="table-wrap">
            <table><thead><tr><th>名称</th><th>用途</th><th>来源</th><th>版本</th><th>状态</th></tr></thead><tbody>
                @forelse ($skills as $skill)
                    <tr><td><strong>{{ $skill->name }}</strong><br><small style="color:var(--muted)">{{ $skill->slug }}</small></td><td>{{ $skill->kind }}</td><td>{{ $skill->source === 'user' ? '用户上传' : '系统预设' }}</td><td>v{{ $skill->current_version }}</td><td><span class="badge {{ $skill->enabled ? 'teal' : 'gray' }}">{{ $skill->enabled ? '启用' : '停用' }}</span></td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><strong>还没有 Skill</strong>可以从系统预设开始，或通过接口上传自定义 Skill。</div></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>
@endsection
