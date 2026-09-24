@extends('admin.layout', ['title' => '标题库'])
@section('content')<h1>标题库</h1><ul>@foreach ($titles as $title)<li>{{ $title->raw_title }} · {{ $title->enabled ? '启用' : '停用' }} · 使用 {{ $title->use_count }} 次</li>@endforeach</ul>{{ $titles->links() }}@endsection
