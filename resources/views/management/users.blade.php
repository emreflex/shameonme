@extends('layouts.app')
@section('title','Kullanıcılar & roller')
@section('content')
<div class="page-heading"><div><h1>Kullanıcılar & roller</h1><p>Yetkiler sunucu tarafında departman ve kayıt sahipliğiyle denetlenir.</p></div></div>
<section class="panel p-6 mb-6"><h2>Yeni kullanıcı</h2><form method="post" action="{{ route('users.store') }}" class="mt-5">@csrf @include('management.user-fields',['u'=>null])<button class="btn primary mt-4">Kullanıcı oluştur</button></form></section>
<section class="panel p-6"><h2>Kullanıcı hesapları</h2><div class="timeline">@foreach($users as $u)<details><summary>{{ $u->name }} · {{ $u->email }} · {{ $u->active?'Aktif':'Pasif' }}</summary><form method="post" action="{{ route('users.update',$u) }}" class="mt-4">@csrf @method('PUT')@include('management.user-fields',['u'=>$u])<button class="btn secondary mt-4">Kaydet</button></form></details>@endforeach</div><div class="mt-5">{{ $users->links() }}</div></section>
@endsection
