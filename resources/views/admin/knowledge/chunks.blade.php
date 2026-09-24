@extends('admin.layout', ['title' => '切片 · '.$document->name])

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow"><a href="{{ route('admin.knowledge.show', $base) }}" style="color:inherit">{{ $base->name }}</a> / 切片</div>
            <h1>{{ $document->name }}</h1>
            <p class="subtitle">共 {{ $chunks->count() }} 个切片 · 文档状态：{{ ['pending' => '待审核', 'reviewed' => '已审核', 'excluded' => '排除'][$document->review_status] ?? $document->review_status }} · 风险：{{ ['low' => '低', 'medium' => '中', 'high' => '高'][$document->risk_level] ?? $document->risk_level }}</p>
        </div>
        <a class="button ghost" href="{{ route('admin.knowledge.show', $base) }}">返回知识库</a>
    </div>

    <section class="panel">
        <div class="panel-header"><div><h2>切片明细</h2><p>文章生成时按相关性选取切片作为引用依据。</p></div></div>
        <div class="panel-body">
            @if ($chunks->isEmpty())
                <div class="empty"><strong>该文档没有切片</strong>可能是空文档或未成功切块。</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>#</th><th>标题</th><th>字数</th><th>内容</th></tr></thead>
                        <tbody>
                        @foreach ($chunks as $chunk)
                            <tr>
                                <td>{{ $chunk->position }}</td>
                                <td>{{ $chunk->heading ?? '—' }}</td>
                                <td>{{ mb_strlen($chunk->content) }}</td>
                                <td style="white-space:normal;min-width:420px;color:#3d4b54">{{ \Illuminate\Support\Str::limit($chunk->content, 300) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
