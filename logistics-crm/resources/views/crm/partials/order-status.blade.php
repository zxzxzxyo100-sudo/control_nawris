@php
    $map = [
        'pending' => ['label' => 'قيد الانتظار', 'class' => 'bg-amber-50 text-amber-900 ring-amber-200'],
        'in_transit' => ['label' => 'قيد التوصيل', 'class' => 'bg-sky-50 text-sky-900 ring-sky-200'],
        'completed' => ['label' => 'مكتمل', 'class' => 'bg-emerald-50 text-emerald-900 ring-emerald-200'],
        'canceled' => ['label' => 'ملغى', 'class' => 'bg-slate-100 text-slate-700 ring-slate-200'],
    ];
    $s = $map[$status] ?? ['label' => $status, 'class' => 'bg-slate-50 text-slate-800 ring-slate-200'];
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $s['class'] }}">{{ $s['label'] }}</span>
