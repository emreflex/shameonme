@extends('layouts.app')
@section('title','Bildirim merkezi')
@section('content')
<div class="page-heading"><div><h1>Bildirim merkezi</h1><p>Kasa uyuşmazlıkları ve kapanmamış vardiyalar.</p></div></div><section class="panel p-6"><div class="timeline">@forelse($items as $item)<a class="notification-item" href="{{ route('shifts.edit',$item->shift_id) }}"><span class="status-icon"><i data-lucide="bell"></i></span><div><strong>{{ $item->message }}</strong><p>Vardiya #{{ $item->shift_id }} · {{ $item->created_at->format('d.m.Y H:i') }}</p></div><i data-lucide="arrow-right"></i></a>@empty<div class="empty"><i data-lucide="circle-check"></i><strong>Bekleyen bildirim yok</strong><p>Kontrol gerektiren işlemler burada görünür.</p></div>@endforelse</div>{{ $items->links() }}</section>
@endsection
