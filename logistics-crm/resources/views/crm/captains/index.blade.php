@extends('crm.layout')

@section('title', 'إدارة الكباتن')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">الكباتن</h1>
            <p class="mt-1 text-sm text-slate-600">إدارة أسطول التوصيل والحالة التشغيلية</p>
        </div>
        <a href="{{ route('crm.captains.create') }}"
           class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
            إضافة كابتن
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-right text-sm">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">الرمز</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">الاسم</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">الهاتف</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">المركبة</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">الحالة</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">طلبات نشطة</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">إجراءات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($captains as $captain)
                    <tr class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $captain->code }}</td>
                        <td class="px-4 py-3 font-medium">{{ $captain->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $captain->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $captain->vehicle_type ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($captain->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200">نشط</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">غير نشط</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $captain->active_orders_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('crm.captains.edit', $captain) }}" class="text-sm font-semibold text-slate-900 underline-offset-2 hover:underline">تعديل</a>
                                <form action="{{ route('crm.captains.destroy', $captain) }}" method="post" onsubmit="return confirm('تأكيد حذف هذا الكابتن؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:underline">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">لا يوجد كباتن مسجّلون بعد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($captains->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $captains->links() }}</div>
        @endif
    </div>
@endsection
