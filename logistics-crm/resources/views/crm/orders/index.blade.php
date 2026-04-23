@extends('crm.layout')

@section('title', 'الطلبات')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">الطلبات</h1>
        <p class="mt-1 text-sm text-slate-600">عرض جميع الشحنات وحالاتها</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-right text-sm">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">المرجع</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">الحالة</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">الكابتن</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">موعد التسليم</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">آخر متابعة</th>
                    <th class="px-4 py-3 font-semibold text-slate-700" title="واتساب مع تسجيل متابعة اليوم في النظام">واتساب</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($orders as $order)
                    @php
                        $lateDays = 0;
                        if ($order->promised_delivery_at && ! $order->isTerminal() && $order->promised_delivery_at->isPast()) {
                            $lateDays = (int) $order->promised_delivery_at->diffInDays(now());
                        }
                    @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-mono font-medium">{{ $order->reference }}</td>
                        <td class="px-4 py-3">@include('crm.partials.order-status', ['status' => $order->status])</td>
                        <td class="px-4 py-3 text-slate-700">{{ $order->captain?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->promised_delivery_at?->translatedFormat('Y/m/d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->last_follow_up_at?->translatedFormat('Y/m/d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">@include('crm.partials.whatsapp-order-actions', ['order' => $order, 'lateDays' => $lateDays])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">لا توجد طلبات.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
