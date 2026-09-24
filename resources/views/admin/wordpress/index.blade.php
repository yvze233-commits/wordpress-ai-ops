@extends('admin.layout', ['title' => 'WordPress 连接'])
@section('content')<h1>WordPress 连接</h1><ul>@foreach ($connections as $connection)<li>{{ $connection->name }} · {{ $connection->base_url }} · {{ $connection->status }} · 默认仅草稿</li>@endforeach</ul>@endsection
