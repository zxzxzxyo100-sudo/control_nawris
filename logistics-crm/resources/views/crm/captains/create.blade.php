@extends('crm.layout')

@section('title', 'إضافة كابتن')

@section('content')
    <div class="mb-6">
        <a href="{{ route('crm.captains.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">← العودة للقائمة</a>
        <h1 class="mt-4 text-2xl font-bold text-slate-900">إضافة كابتن جديد</h1>
    </div>

    <form action="{{ route('crm.captains.store') }}" method="post" class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('crm.captains._form', ['captain' => null])
        <div class="mt-8 flex gap-3">
            <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">حفظ</button>
            <a href="{{ route('crm.captains.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">إلغاء</a>
        </div>
    </form>
@endsection
