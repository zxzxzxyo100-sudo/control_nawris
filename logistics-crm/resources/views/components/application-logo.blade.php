@props([
    'showTagline' => true,
    /** للعرض فوق خلفيات داكنة (مثل صفحة الدخول) */
    'variant' => 'default',
])

@php
    $light = $variant === 'light';
@endphp

{{--
    شعار شركة النوارس — أيقونة طائر/جناح. لاستبدال بشعار رسمي:
    <img src="{{ asset('images/nawras-logo.png') }}" alt="النوارس إكسبريس" class="h-14 w-auto" />
--}}
<div {{ $attributes->class('flex flex-col items-center gap-2 text-center ' . ($light ? 'text-white' : 'text-slate-900')) }}>
    <div class="flex items-center justify-center gap-3">
        <svg class="{{ $light ? 'text-sky-100' : 'text-sky-600' }} h-14 w-14 shrink-0 drop-shadow sm:h-16 sm:w-16" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="32" cy="34" r="28" class="{{ $light ? 'fill-white/10 stroke-white/35' : 'fill-sky-50 stroke-sky-200' }}" stroke-width="1.5"/>
            <path d="M12 38c8-14 22-22 36-20 2 0 4 1 5 3-6 2-12 6-16 11-4 5-7 12-8 18-6-4-10-8-17-12z" class="{{ $light ? 'fill-sky-200' : 'fill-sky-500' }}" opacity=".95"/>
            <path d="M28 22c10-6 20-4 26 4-8 0-16 4-22 10-3 3-5 7-6 11l-5-3c1-8 3-16 7-22z" class="{{ $light ? 'fill-white' : 'fill-sky-700' }}"/>
            <path d="M44 18c4 2 7 6 8 11-3-1-6-1-9 0 1-4 1-8 1-11z" class="fill-amber-400"/>
        </svg>
        <div class="text-right">
            <p class="text-lg font-black tracking-tight sm:text-xl">النوارس إكسبريس</p>
            <p class="text-xs font-semibold sm:text-sm {{ $light ? 'text-sky-100' : 'text-sky-700' }}">شركة النوارس للتوصيل والشحن</p>
        </div>
    </div>
    @if($showTagline)
        <p class="max-w-xs text-[11px] leading-relaxed {{ $light ? 'text-white/80' : 'text-slate-500' }}">سرعة · أمان · تغطية لوجستية موثوقة</p>
    @endif
</div>
