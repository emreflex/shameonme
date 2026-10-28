@extends('layouts.app')
@section('title','Raporlar & analiz')
@section('content')
@php($posChart=collect(["Kuveyt"=>"pos_kuveyt","Garanti"=>"pos_garanti","Ziraat"=>"pos_ziraat","Diğer"=>"pos_other"])->map(fn($key)=>\App\Services\Money::cents($summary["totals"][$key])))
<div class="page-heading"><div><span class="eyebrow">VERİDEN GÖRÜNÜRLÜĞE</span><h1>Raporlar & analiz</h1><p>Gerçek kayıtlar üzerinden dönem, departman ve personel analizi.</p></div><div class="flex gap-2 print:hidden"><button class="btn secondary" onclick="window.print()"><i data-lucide="printer"></i>Yazdır</button><a class="btn secondary" href="{{ request()->fullUrlWithQuery(['output'=>'pdf','page'=>null]) }}">PDF</a><a class="btn primary" href="{{ request()->fullUrlWithQuery(['output'=>'xlsx','page'=>null]) }}"><i data-lucide="download"></i>Excel</a></div></div>
@include('partials.filters')
<div class="mini-metrics"><div><span>Toplam satış</span><strong>{{ \App\Services\Money::format($summary['totals']['sales']) }}</strong></div><div><span>Toplam POS</span><strong>{{ \App\Services\Money::format($summary['totals']['pos_total']) }}</strong></div><div><span>Toplam gider</span><strong>{{ \App\Services\Money::format($summary['totals']['expense']) }}</strong></div><div><span>Kasa farkı</span><strong>{{ \App\Services\Money::format($summary['totals']['difference']) }}</strong></div></div>
@include('partials.analysis')
<section class="panel mb-6"><div class="panel-heading"><h2>Kasa durumu dağılımı</h2><small>Sayım bekleyenler eksik kasa sayılmaz.</small></div><div class="mini-metrics">@foreach($summary['cash'] as $key=>$count)<div><span>{{ __('ui.cash.'.$key) }}</span><strong class="cash-{{ $key }}">{{ $count }}</strong></div>@endforeach</div></section>
@include('reports.breakdown')
<section class="panel"><div class="panel-heading"><div><h2>Rapor kayıtları</h2><p>{{ $range[0] }} — {{ $range[1] }} · {{ $summary['count'] }} vardiya</p></div></div>@include('partials.table',['shifts'=>$rows])<div class="p-5">{{ $rows->links() }}</div></section>
@endsection
