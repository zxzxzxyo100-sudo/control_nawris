@extends('crm.layout')

@section('title', 'لوحة التحكم')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">لوحة التحكم التشغيلية</h1>
        <p class="mt-1 text-sm text-slate-600">مؤشرات حية لتوزيع الطلبات، أداء الكباتن، وتقرير التأخير الحرج</p>
    </div>

    <section id="kpi-followup-card"
             class="mb-6 rounded-2xl border border-emerald-600/30 p-6 shadow-lg"
             style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);"
             title="عدد الطلبات التي سُجّل لها وقت متابعة (last_follow_up_at) اليوم">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-white/90">متابعات اليوم</p>
                <p class="mt-1 text-xs text-white/80">يشمل تسجيل المتابعة عبر واتساب أو حفظ ملاحظات المتابعة</p>
            </div>
            <div class="flex items-center gap-3">
                <i class="fa-brands fa-whatsapp text-4xl text-white/90" aria-hidden="true"></i>
                <span id="kpi-daily-followup-value" class="text-5xl font-black tabular-nums text-white drop-shadow-sm">—</span>
            </div>
        </div>
    </section>

    <div id="dash-error" class="mb-4 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-bold text-slate-900">توزيع الطلبات حسب الحالة</h2>
            <div class="mx-auto h-64 max-w-sm">
                <canvas id="chart-status" aria-label="رسم توزيع الحالات"></canvas>
            </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-bold text-slate-900">أفضل 5 كباتن (حسب الطلبات المكتملة)</h2>
            <div class="h-64">
                <canvas id="chart-top-captains" aria-label="رسم أداء الكباتن"></canvas>
            </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 text-lg font-bold text-slate-900">تقرير التأخير: طلبات متأخرة 5 أيام فأكثر لكل كابتن</h2>
            <div class="h-72">
                <canvas id="chart-delay-captains" aria-label="رسم التأخير حسب الكابتن"></canvas>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const statusAr = {
                pending: 'قيد الانتظار',
                in_transit: 'قيد التوصيل',
                completed: 'مكتمل',
                canceled: 'ملغى'
            };
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const errEl = document.getElementById('dash-error');

            const palette = ['#0f172a', '#334155', '#64748b', '#94a3b8', '#cbd5e1', '#e2e8f0'];

            let chartStatus, chartTop, chartDelay;

            async function load() {
                errEl.classList.add('hidden');
                try {
                    const res = await fetch(@json(route('crm.dashboard.api.analytics')), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token || ''
                        },
                        credentials: 'same-origin'
                    });
                    const body = await res.json().catch(() => null);
                    if (!res.ok) throw new Error(body?.message || res.status);
                    const d = body.data || {};

                    const kpiEl = document.getElementById('kpi-daily-followup-value');
                    if (kpiEl) kpiEl.textContent = String(d.daily_followup_count ?? 0);

                    const ob = d.orders_by_status || {};
                    const labelsS = Object.keys(ob).map(k => statusAr[k] || k);
                    const dataS = Object.keys(ob).map(k => ob[k]);

                    if (chartStatus) chartStatus.destroy();
                    chartStatus = new Chart(document.getElementById('chart-status'), {
                        type: 'doughnut',
                        data: {
                            labels: labelsS,
                            datasets: [{
                                data: dataS,
                                backgroundColor: palette,
                                borderWidth: 1,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            plugins: { legend: { position: 'bottom', labels: { font: { family: 'Tahoma' } } } },
                            maintainAspectRatio: false
                        }
                    });

                    const top = d.top_captains || [];
                    if (chartTop) chartTop.destroy();
                    chartTop = new Chart(document.getElementById('chart-top-captains'), {
                        type: 'bar',
                        data: {
                            labels: top.map(r => r.full_name),
                            datasets: [
                                {
                                    label: 'مكتمل',
                                    data: top.map(r => r.completed_orders),
                                    backgroundColor: '#0f172a'
                                },
                                {
                                    label: 'نشط الآن',
                                    data: top.map(r => r.active_orders),
                                    backgroundColor: '#94a3b8'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { ticks: { font: { family: 'Tahoma' } } },
                                y: { beginAtZero: true, ticks: { stepSize: 1 } }
                            },
                            plugins: { legend: { labels: { font: { family: 'Tahoma' } } } }
                        }
                    });

                    const del = d.delay_over_5d_by_captain || [];
                    if (chartDelay) chartDelay.destroy();
                    chartDelay = new Chart(document.getElementById('chart-delay-captains'), {
                        type: 'bar',
                        data: {
                            labels: del.map(r => r.full_name || r.code),
                            datasets: [{
                                label: 'عدد الطلبات المتأخرة (≥5 أيام)',
                                data: del.map(r => r.delayed_orders),
                                backgroundColor: '#b91c1c'
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { beginAtZero: true, ticks: { stepSize: 1 } }
                            },
                            plugins: { legend: { labels: { font: { family: 'Tahoma' } } } }
                        }
                    });
                } catch (e) {
                    errEl.textContent = 'تعذر تحميل بيانات الرسوم: ' + (e.message || '');
                    errEl.classList.remove('hidden');
                }
            }

            load();
        })();
    </script>
@endpush
