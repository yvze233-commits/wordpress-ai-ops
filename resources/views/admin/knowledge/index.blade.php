@extends('admin.layout', ['title' => '知识库'])
@section('content')<h1>知识库</h1><ul>@foreach ($bases as $base)<li>{{ $base->name }} · {{ $base->enabled ? '启用' : '停用' }} · {{ $base->documents_count }} 个文档</li>@endforeach</ul>@endsection
