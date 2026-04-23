<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaptainRequest;
use App\Http\Requests\UpdateCaptainRequest;
use App\Models\Captain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CaptainController extends Controller
{
    public function index(): View
    {
        $captains = Captain::query()
            ->withCount(['orders as active_orders_count' => fn ($q) => $q->whereNotIn('status', \App\Models\Order::TERMINAL_STATUSES)])
            ->orderBy('full_name')
            ->paginate(15);

        return view('crm.captains.index', compact('captains'));
    }

    public function create(): View
    {
        return view('crm.captains.create');
    }

    public function store(StoreCaptainRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $next = ((int) Captain::query()->lockForUpdate()->max('id')) + 1;
            $data['code'] = 'CAP-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            Captain::query()->create($data);
        });

        return redirect()->route('crm.captains.index')->with('success', 'تم إضافة الكابتن بنجاح.');
    }

    public function edit(Captain $captain): View
    {
        return view('crm.captains.edit', compact('captain'));
    }

    public function update(UpdateCaptainRequest $request, Captain $captain): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $captain->update($data);

        return redirect()->route('crm.captains.index')->with('success', 'تم تحديث بيانات الكابتن.');
    }

    public function destroy(Captain $captain): RedirectResponse
    {
        $captain->delete();

        return redirect()->route('crm.captains.index')->with('success', 'تم حذف الكابتن.');
    }
}
