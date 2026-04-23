@php
    $c = $captain ?? null;
    $isEdit = (bool) $c;
@endphp

<div class="space-y-5">
    <div>
        <label for="full_name" class="mb-1.5 block text-sm font-semibold text-slate-700">الاسم الكامل</label>
        <input type="text" name="full_name" id="full_name" required
               value="{{ old('full_name', $c->full_name ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
               placeholder="اسم الكابتن">
        @error('full_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700">رقم الهاتف</label>
        <input type="text" name="phone" id="phone"
               value="{{ old('phone', $c->phone ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
               placeholder="+355...">
        @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="vehicle_type" class="mb-1.5 block text-sm font-semibold text-slate-700">نوع المركبة</label>
        <input type="text" name="vehicle_type" id="vehicle_type"
               value="{{ old('vehicle_type', $c->vehicle_type ?? '') }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
               placeholder="فان، دراجة، شاحنة صغيرة...">
        @error('vehicle_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               class="h-4 w-4 rounded border-slate-300 text-slate-900"
               @checked((string) old('is_active', ($c->is_active ?? true) ? '1' : '0') === '1')>
        <label for="is_active" class="text-sm font-medium text-slate-800">الحالة: نشط</label>
    </div>
</div>
