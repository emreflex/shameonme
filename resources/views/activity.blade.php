@extends('layouts.app')
@section('title','İşlem geçmişi')
@section('content')
<div class="page-heading"><div><h1>İşlem geçmişi</h1><p>Finansal kayıtlar ve yönetim işlemlerinin denetim izi.</p></div></div><section class="panel p-6"><div class="timeline">@forelse($logs as $log)<details><summary>{{ $log->created_at->format('d.m.Y H:i:s') }} · {{ $log->action }} · {{ $log->subject_type }} #{{ $log->subject_id }} · Kullanıcı #{{ $log->user_id }}</summary><p>{{ $log->reason }}</p><div class="audit-comparison"><pre>{{ json_encode($log->before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre><pre>{{ json_encode($log->after,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></div></details>@empty<p class="muted">Henüz işlem bulunmuyor.</p>@endforelse</div><div class="mt-5">{{ $logs->links() }}</div></section>
@endsection
