@extends('admin.layout', ['title' => '批次'])
@section('content')<h1>批次</h1><ul>@foreach ($batches as $batch)<li><a href="{{ route('admin.batches.show', $batch) }}">{{ $batch->run_date->toDateString() }}</a> · {{ $batch->status }} · {{ $batch->completed_count }}/{{ $batch->target_count }}</li>@endforeach</ul>{{ $batches->links() }}@endsection
