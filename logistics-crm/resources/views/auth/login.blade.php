{{--
    صفحة دخول — شركة النوارس للتوصيل والشحن (Al-Nawras Express).
    انسخ إلى: resources/views/auth/login.blade.php (Laravel Breeze).
    يعتمد على مكوّنات Breeze: x-auth-session-status، x-input-error.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول — النوارس إكسبريس</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .nawras-login-bg {
            background-image:
                linear-gradient(120deg, rgba(12, 74, 110, 0.92) 0%, rgba(3, 105, 161, 0.88) 45%, rgba(14, 165, 233, 0.82) 100%),
                url("https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=2000&q=80");
            background-size: cover;
            background-position: center;
        }
        body.nawras-login { font-family: 'Tajawal', Tahoma, sans-serif; }
    </style>
</head>
<body class="nawras-login min-h-screen antialiased text-slate-900">
    <div class="nawras-login-bg flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
        <div class="mb-6 w-full max-w-md text-center text-white drop-shadow-sm">
            <x-application-logo variant="light" :show-tagline="false" />
            <h1 class="mt-5 text-xl font-extrabold leading-snug sm:text-2xl">
                نظام إدارة العمليات — النوارس إكسبريس
            </h1>
            <p class="mt-2 text-sm text-white/85">الدخول الآمن لموظفي العمليات والمتابعة</p>
        </div>

        <div class="w-full max-w-md rounded-xl bg-white/95 p-6 shadow-2xl ring-1 ring-white/40 backdrop-blur-sm sm:p-8">
            <x-auth-session-status class="mb-4 text-center text-sm text-sky-800" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-bold text-slate-800">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-red-600" />
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-bold text-slate-800">كلمة المرور</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-red-600" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2">
                        <input id="remember_me" type="checkbox" name="remember"
                               class="h-4 w-4 rounded border-slate-300 text-sky-600 shadow-sm focus:ring-sky-500" />
                        <span class="text-sm font-medium text-slate-700">تذكرني</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="text-sm font-semibold text-sky-700 underline-offset-2 hover:text-sky-900 hover:underline" href="{{ route('password.request') }}">
                            نسيت كلمة المرور؟
                        </a>
                    @endif
                </div>

                <button type="submit"
                        class="flex w-full justify-center rounded-lg bg-gradient-to-l from-sky-700 to-sky-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-sky-900/20 transition hover:from-sky-800 hover:to-sky-700 focus:outline-none focus:ring-4 focus:ring-sky-500/40">
                    تسجيل الدخول
                </button>
            </form>
        </div>

        <p class="mt-8 max-w-lg text-center text-xs font-medium leading-relaxed text-white/90 drop-shadow">
            جميع الحقوق محفوظة © {{ date('Y') }} شركة النوارس للتوصيل والشحن
        </p>
    </div>
</body>
</html>
