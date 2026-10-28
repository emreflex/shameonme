@extends('layouts.app')
@section('title','Vardiya kayıtları')
@section('content')
<div class="page-heading"><div><span class="eyebrow">GÜNLÜK OPERASYON</span><h1>Vardiya kayıtları</h1><p>Petrol ve market kasalarınızı bağımsız olarak yönetin.</p></div><a class="btn primary" href="{{ route('shifts.create') }}"><i data-lucide="plus"></i>Yeni vardiya</a></div>
<form class="filter-bar"><input name="search" value="{{ request('search') }}" placeholder="Personel adına göre ara…"><select name="department"><option value="">Tüm departmanlar</option>@foreach(['petrol'=>'Petrol','market'=>'Market'] as $k=>$v)<option value="{{ $k }}" @selected(request('department')===$k)>{{ $v }}</option>@endforeach</select><input type="date" name="date" value="{{ request('date') }}" aria-label="Tarih"><select name="status"><option value="">Tüm durumlar</option>@foreach(__('ui.status') as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select><button class="btn secondary small">Filtrele</button></form>
<section class="panel">@include('partials.table',['shifts'=>$shifts])<div class="p-5">{{ $shifts->links() }}</div></section>
@endsection
