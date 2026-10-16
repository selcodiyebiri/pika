@extends('layouts.admin')

@section('title', $title . ' · Pika Admin')
@section('heading', $title)

@section('content')
    <section class="card">
        <div class="card-head"><h2>Scheduled jobs</h2><span class="badge">{!! icon('terminal', 'icon icon-sm') !!} php pika job:run &lt;name&gt;</span></div>
        @if (!empty($jobs))
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Job</th><th>Last run</th><th>Result</th><th>Took</th><th>Failed in a row</th></tr></thead>
                    <tbody>
                    @foreach ($jobs as $job)
                        <tr>
                            <td><strong>{{ $job['name'] }}</strong>@if ($job['message'] !== '')<div class="muted">{{ $job['message'] }}</div>@endif</td>
                            <td class="muted">{{ $job['when'] }}</td>
                            <td><span class="badge {{ $job['tone'] }}">{{ $job['status'] }}</span></td>
                            <td class="muted">{{ $job['seconds'] !== '' ? $job['seconds'] . ' s' : '' }}</td>
                            <td class="muted">{{ $job['failures'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">No jobs yet. Create one with <code>php pika make:job name</code>.</div>
        @endif
    </section>
@endsection
