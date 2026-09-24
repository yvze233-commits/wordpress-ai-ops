@extends('admin.layout', ['title' => '热点来源'])
@section('content')<h1>热点来源</h1><ul>@foreach ($sources as $source)<li>{{ $source->name }} · {{ $source->type }} · {{ $source->enabled ? '启用' : '停用' }} · {{ $source->feeds_count }} 条</li>@endforeach</ul>@endsection
