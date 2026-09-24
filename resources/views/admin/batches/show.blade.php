@extends('admin.layout', ['title' => '批次详情'])
@section('content')<h1>{{ $batch->run_date->toDateString() }}</h1><p>{{ $batch->status }} · {{ $batch->completed_count }}/{{ $batch->target_count }}</p><ul>@foreach ($batch->contentItems as $item)<li><a href="{{ route('admin.reviews.show', $item) }}">{{ $item->title }}</a> · {{ $item->state }}</li>@endforeach</ul>@endsection
