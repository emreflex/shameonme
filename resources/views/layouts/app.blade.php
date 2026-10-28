<!doctype html>
<html lang="tr" x-data="{dark:localStorage.theme==='dark', mobile:false, compact:false}" :class="{'dark':dark}" x-cloak>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Yönetim paneli') · İstasyon</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body>
<div class="shell" :class="{'compact':compact}">
<aside class="sidebar print:hidden" :class="{'mobile-open':mobile}">
 <a href="{{ route('dashboard') }}" class="brand"><span class="brand-icon"><i data-lucide="fuel"></i></span><span class="nav-label">istasyon<span class="brand-dot">.</span><small>İŞLETME YÖNETİMİ</small></span></a>
 <p class="nav-caption nav-label">ÇALIŞMA ALANI</p>
 @php($links=[['dashboard','layout-dashboard','Genel bakış'],['shifts.index','clipboard-list','Vardiya kayıtları'],['daily','scan-line','Günlük kasa kontrolü'],['reports.index','chart-no-axes-combined','Raporlar & analiz']])
 @foreach($links as [$route,$icon,$text])<a class="nav-item {{ request()->routeIs($route)?'selected':'' }}" href="{{ route($route) }}"><i data-lucide="{{ $icon }}"></i><span class="nav-label">{{ $text }}</span></a>@endforeach
 @unless(auth()->user()->isStaff())
 <p class="nav-caption nav-label">YÖNETİM</p>
 @foreach([['employees','users-round','Personel'],['imports','file-spreadsheet','Excel aktarımı'],['activity','history','İşlem geçmişi']] as [$route,$icon,$text])<a class="nav-item {{ request()->routeIs($route.'*')?'selected':'' }}" href="{{ route($route) }}"><i data-lucide="{{ $icon }}"></i><span class="nav-label">{{ $text }}</span></a>@endforeach
 @endunless
 @if(auth()->user()->isSuper())
 <a class="nav-item {{ request()->routeIs('users')?'selected':'' }}" href="{{ route('users') }}"><i data-lucide="shield-check"></i><span class="nav-label">Kullanıcılar & roller</span></a>
 <a class="nav-item {{ request()->routeIs('settings')?'selected':'' }}" href="{{ route('settings') }}"><i data-lucide="settings-2"></i><span class="nav-label">Sistem ayarları</span></a>@endif
 <div class="sidebar-bottom"><div class="environment nav-label"><span class="live-dot"></span> Kayıtlarınız güvenle saklanır<small>Europe/Istanbul · Türk Lirası</small></div><button @click="compact=!compact" class="nav-item desktop-toggle"><i data-lucide="panel-left-close"></i><span class="nav-label">Menüyü daralt</span></button></div>
</aside>
<div class="workspace">
<header class="topbar print:hidden"><div class="flex items-center gap-3"><button @click="mobile=!mobile" class="icon-btn mobile-toggle" aria-label="Menü"><i data-lucide="menu"></i></button><span class="breadcrumb">{{ \App\Models\ApplicationSetting::where('key','business_name')->value('value') }} <span>/</span> <strong>@yield('title','Genel bakış')</strong></span></div><div class="flex items-center gap-3"><span class="date-label">{{ now()->format('d.m.Y') }}</span><button class="icon-btn" @click="dark=!dark;localStorage.theme=dark?'dark':'light'" aria-label="Temayı değiştir"><i data-lucide="sun-moon"></i></button><a href="{{ route('notifications') }}" class="icon-btn" aria-label="Bildirimler"><i data-lucide="bell"></i></a><span class="topbar-divider"></span><div class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</div><div class="user-meta"><strong>{{ auth()->user()->name }}</strong><small>{{ ['super_admin'=>'Süper yönetici','manager'=>'Yönetici','staff'=>'Personel'][auth()->user()->role->name] }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="Çıkış yap"><i data-lucide="log-out"></i></button></form></div></header>
<main>
@if(session('success'))<div class="alert success" role="status"><i data-lucide="circle-check"></i>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert danger" role="alert"><div><strong>İşlem tamamlanamadı</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
@yield('content')
<footer>İstasyon · Petrol & Market Yönetimi <span>Finansal görünürlük. Kontrollü operasyon.</span></footer>
</main></div></div>
@stack('scripts')
</body></html>
