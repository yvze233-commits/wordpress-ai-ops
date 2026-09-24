@extends('admin.layout', ['title' => '图片库'])
@section('content')<h1>图片库</h1><ul>@foreach ($libraries as $library)<li>{{ $library->name }} · {{ $library->enabled ? '启用' : '停用' }} · {{ $library->images_count }} 张</li>@endforeach</ul>@endsection
