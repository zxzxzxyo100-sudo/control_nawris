<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام إدارة العمليات') — CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Tahoma', 'Segoe UI', 'sans-serif'] } } } }
    </script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
<div class="flex min-h-screen flex-row-reverse">
    <aside class="hidden w-64 shrink-0 flex-col border-l border-slate-800 bg-slate-900 text-white md:flex">
        <div class="border-b border-slate-800 px-5 py-6">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">لوجستيات</p>
            <p class="text-lg font-bold">لوحة التحكم</p>
        </div>
        <nav class="flex flex-1 flex-col gap-1 p-3 text-sm">
            <a href="{{ route('crm.dashboard') }}"
               class="rounded-lg px-3 py-2.5 font-medium transition {{ request()->routeIs('crm.dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/80' }}">
                الرئيسية
            </a>
            <a href="{{ route('crm.orders.index') }}"
               class="rounded-lg px-3 py-2.5 font-medium transition {{ request()->routeIs('crm.orders.index') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/80' }}">
                الطلبات
            </a>
            <a href="{{ route('crm.captains.index') }}"
               class="rounded-lg px-3 py-2.5 font-medium transition {{ request()->routeIs('crm.captains.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/80' }}">
                الكباتن
            </a>
            <a href="{{ route('crm.orders.follow-up') }}"
               class="rounded-lg px-3 py-2.5 font-medium transition {{ request()->routeIs('crm.orders.follow-up') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/80' }}">
                قائمة المتابعة
            </a>
        </nav>
        <div class="border-t border-slate-800 p-4 text-xs text-slate-500">
            واجهة داخلية آمنة — الجلسة وCSRF
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur md:hidden">
            <span class="font-bold">CRM</span>
            <nav class="flex flex-wrap gap-2 text-xs">
                <a class="rounded bg-slate-900 px-2 py-1 text-white" href="{{ route('crm.dashboard') }}">الرئيسية</a>
                <a class="rounded border border-slate-300 px-2 py-1" href="{{ route('crm.orders.index') }}">الطلبات</a>
                <a class="rounded border border-slate-300 px-2 py-1" href="{{ route('crm.captains.index') }}">الكباتن</a>
                <a class="rounded border border-slate-300 px-2 py-1" href="{{ route('crm.orders.follow-up') }}">متابعة</a>
            </nav>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ session('error') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>
    (function () {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a.js-wa-log-followup');
            if (!link) return;
            e.preventDefault();
            const target = link.getAttribute('href');
            const logUrl = link.getAttribute('data-log-url');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            if (!logUrl || !target) return;
            fetch(logUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                credentials: 'same-origin',
                body: JSON.stringify({})
            }).then(function (r) {
                if (!r.ok) throw new Error('log failed');
                return r.json();
            }).then(function () {
                window.open(target, '_blank', 'noopener,noreferrer');
            }).catch(function () {
                alert('تعذر تسجيل المتابعة على الخادم. تحقق من الجلسة وحاول مجدداً.');
            });
        });
    })();
</script>
@stack('scripts')
</body>
</html>
