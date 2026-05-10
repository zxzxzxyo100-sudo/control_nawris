import type { ReactNode } from 'react';
import {
  Activity,
  Gauge,
  Hourglass,
  Minus,
  TrendingDown,
  TrendingUp,
} from 'lucide-react';

/** Snapshot from the previous 24h for trend comparison (same shape as inputs). */
export interface ControlTowerStatsPrevious {
  ordersWithAgentOver96h?: number;
  totalOrdersWithAgents?: number;
  totalPendingReturns?: number;
  avgDailyReturnProcessingVolume?: number;
  currentDelayedOrders?: number;
  avgDailyCompletionRate?: number;
  /** Pinned ~24h ago: كفاءة تفريغ المرتجعات مع المندوب (0–100) لمقارنة الاتجاه */
  returnDischargePerformanceScore?: number;
}

/**
 * Props for Control Tower reverse KPIs (lower value = better performance).
 * Provide raw counts; component derives rates. Optional `previous` powers 24h trends.
 */
export interface ControlTowerStatsData {
  ordersWithAgentOver96h: number;
  totalOrdersWithAgents: number;
  totalPendingReturns: number;
  /** Average returns processed per day (denominator for aging). Must be > 0 for a finite ratio. */
  avgDailyReturnProcessingVolume: number;
  /** Current delayed orders backlog (numerator for density). */
  currentDelayedOrders: number;
  /** Average orders completed per day (denominator for backlog density). Must be > 0 for a finite ratio. */
  avgDailyCompletionRate: number;
  previous?: ControlTowerStatsPrevious;
}

/** صف من الـ API قد يحتفظ بحقول تاريخ إضافية (مفاتيح شائعة مذكورة في الدالة). */
export type MixedApiOrder = Record<string, unknown>;

export interface ControlTowerStatisticsProps {
  statsData: ControlTowerStatsData;
  /**
   * عند تمريرها، بطاقة «المرتجعات مع المندوب» تستخدم المنطق الجديد:
   * تصفية `مرتجع مع المندوب` فقط، متوسط العمر بالأيام، ودرجة الكفاءة.
   * بدونها تُعرض بطاقة «أعمار المرتجعات المعلقة» القديمة (المجاميع).
   */
  mixedApiOrders?: readonly MixedApiOrder[];
  className?: string;
}

type Zone = 'green' | 'yellow' | 'red';

/** Reverse KPI: green below target, yellow between target and soft danger band, red at/above danger. */
function reverseKpiZone(
  value: number,
  target: number,
  dangerMultiplier = 1.6,
): Zone {
  if (!Number.isFinite(value)) return 'yellow';
  if (value < target) return 'green';
  const danger = target * dangerMultiplier;
  if (value < danger) return 'yellow';
  return 'red';
}

function agentStagnationRate(data: ControlTowerStatsData): number {
  const d = data.totalOrdersWithAgents;
  if (!d || d <= 0) return 0;
  return (data.ordersWithAgentOver96h / d) * 100;
}

function pendingReturnsAgingDays(data: ControlTowerStatsData): number {
  const vol = data.avgDailyReturnProcessingVolume;
  if (!vol || vol <= 0) return Number.POSITIVE_INFINITY;
  return data.totalPendingReturns / vol;
}

function backlogDensityIndex(data: ControlTowerStatsData): number {
  const rate = data.avgDailyCompletionRate;
  if (!rate || rate <= 0) return Number.POSITIVE_INFINITY;
  return data.currentDelayedOrders / rate;
}

function prevAgentStagnationRate(prev: ControlTowerStatsPrevious): number | null {
  const d = prev.totalOrdersWithAgents;
  if (d == null || d <= 0) return null;
  const n = prev.ordersWithAgentOver96h ?? 0;
  return (n / d) * 100;
}

function prevPendingReturnsAgingDays(prev: ControlTowerStatsPrevious): number | null {
  const vol = prev.avgDailyReturnProcessingVolume;
  if (vol == null || vol <= 0) return null;
  const pending = prev.totalPendingReturns ?? 0;
  return pending / vol;
}

function prevBacklogDensity(prev: ControlTowerStatsPrevious): number | null {
  const rate = prev.avgDailyCompletionRate;
  if (rate == null || rate <= 0) return null;
  const delayed = prev.currentDelayedOrders ?? 0;
  return delayed / rate;
}

/** For reverse KPIs: metric up => worse => trending bad */
function reverseTrendDelta(current: number, previous: number | null): 'up' | 'down' | 'flat' {
  if (previous == null || !Number.isFinite(previous) || !Number.isFinite(current)) return 'flat';
  const eps = Math.max(1e-6, Math.abs(previous) * 1e-4);
  if (current > previous + eps) return 'up';
  if (current < previous - eps) return 'down';
  return 'flat';
}

/** لمؤشرات مباشرة: ارتفاع القيمة = أداء أفضل */
function forwardTrendDelta(current: number, previous: number | null): 'up' | 'down' | 'flat' {
  if (previous == null || !Number.isFinite(previous) || !Number.isFinite(current)) return 'flat';
  const eps = 0.25;
  if (current > previous + eps) return 'up';
  if (current < previous - eps) return 'down';
  return 'flat';
}

const RETURN_WITH_DRIVER_STATUS = 'مرتجع مع المندوب';

function rawOrderStatus(o: MixedApiOrder): string {
  const v = o.status ?? o.order_status ?? o.state;
  return String(v ?? '').trim();
}

/** أيام منذ تحول الحالة إلى مرتجع (أول تاريخ صالح من الحقول المعروفة). */
function daysSinceReturnTransition(o: MixedApiOrder): number | null {
  const keys = [
    'returned_at',
    'returnedAt',
    'status_changed_at',
    'statusChangedAt',
    'return_status_at',
    'updated_at',
    'updatedAt',
    'last_status_change',
    'lastStatusChange',
  ] as const;
  for (const k of keys) {
    const v = o[k];
    if (v == null) continue;
    const t =
      typeof v === 'number'
        ? v > 1e12
          ? v
          : v * 1000
        : Date.parse(String(v));
    if (Number.isFinite(t)) {
      return (Date.now() - t) / 86400000;
    }
  }
  return null;
}

function computeReturnDischargeEfficiency(orders: readonly MixedApiOrder[]): {
  filteredCount: number;
  scoredCount: number;
  averageAge: number;
  performanceScore: number;
} {
  const filtered = orders.filter((o) => rawOrderStatus(o) === RETURN_WITH_DRIVER_STATUS);
  const ages: number[] = [];
  for (const o of filtered) {
    const d = daysSinceReturnTransition(o);
    if (d != null && Number.isFinite(d) && d >= 0) ages.push(d);
  }
  const scoredCount = ages.length;
  const averageAge = scoredCount > 0 ? ages.reduce((a, b) => a + b, 0) / scoredCount : 0;
  const performanceScore =
    scoredCount === 0 ? 100 : Math.max(0, 100 - (averageAge - 3) * 10);
  return {
    filteredCount: filtered.length,
    scoredCount,
    averageAge,
    performanceScore,
  };
}

/** أخضر ≥90، برتقالي 70–89، أحمر <70 */
function efficiencyScoreZone(score: number): Zone {
  if (!Number.isFinite(score)) return 'yellow';
  if (score >= 90) return 'green';
  if (score >= 70) return 'yellow';
  return 'red';
}

const titleTone: Record<Zone, string> = {
  green: 'text-emerald-200',
  yellow: 'text-amber-200',
  red: 'text-rose-200',
};

const zoneStyles: Record<
  Zone,
  {
    ring: string;
    badge: string;
    value: string;
    panel: string;
  }
> = {
  green: {
    ring: 'ring-emerald-500/35',
    badge: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
    value: 'text-emerald-300',
    panel: 'from-emerald-500/10 via-zinc-900/80 to-zinc-950',
  },
  yellow: {
    ring: 'ring-amber-500/40',
    badge: 'bg-amber-500/15 text-amber-200 border-amber-500/35',
    value: 'text-amber-200',
    panel: 'from-amber-500/10 via-zinc-900/80 to-zinc-950',
  },
  red: {
    ring: 'ring-rose-500/45',
    badge: 'bg-rose-500/15 text-rose-300 border-rose-500/35',
    value: 'text-rose-300',
    panel: 'from-rose-500/12 via-zinc-900/80 to-zinc-950',
  },
};

function TrendGlyph(props: {
  trend: 'up' | 'down' | 'flat';
  title: string;
  /** forward: صعود = أفضل (مثل درجة الكفاءة). reverse: افتراضي لمؤشرات عكسية */
  variant?: 'reverse' | 'forward';
}) {
  const { trend, title, variant = 'reverse' } = props;
  const icon =
    trend === 'up' ? (
      <TrendingUp className="h-4 w-4" aria-hidden />
    ) : trend === 'down' ? (
      <TrendingDown className="h-4 w-4" aria-hidden />
    ) : (
      <Minus className="h-4 w-4" aria-hidden />
    );
  const trendGood = variant === 'forward' ? trend === 'up' : trend === 'down';
  const trendBad = variant === 'forward' ? trend === 'down' : trend === 'up';
  const cls = trendBad
    ? 'text-rose-400/90 bg-rose-500/10 border border-rose-500/25'
    : trendGood
      ? 'text-emerald-400/90 bg-emerald-500/10 border border-emerald-500/25'
      : 'text-zinc-500 bg-zinc-800/60 border border-zinc-700/50';

  return (
    <span
      className={`inline-flex items-center justify-center rounded-lg p-1.5 ${cls}`}
      title={title}
      role="img"
    >
      {icon}
    </span>
  );
}

function MetricCard(props: {
  title: string;
  subtitle: string;
  targetLabel: string;
  valueDisplay: string;
  zone: Zone;
  trend: 'up' | 'down' | 'flat';
  trendTitle: string;
  icon: ReactNode;
  trendVariant?: 'reverse' | 'forward';
  /** نص أسفل الرقم الكبير (مثل شرح متوسط العمر) */
  valueCaption?: string;
  /** عند التفعيل يلون العنوان حسب منطقة الأداء (للبطاقات التي تطلب ذلك صراحة) */
  dynamicTitleColor?: boolean;
}) {
  const z = zoneStyles[props.zone];
  const titleCls = props.dynamicTitleColor ? titleTone[props.zone] : 'text-zinc-100';
  return (
    <div
      className={`relative overflow-hidden rounded-2xl border border-zinc-800/80 bg-gradient-to-br ${z.panel} p-5 shadow-lg shadow-black/30 ring-1 ring-inset ${z.ring}`}
    >
      <div className="mb-4 flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-zinc-900/90 ring-1 ring-zinc-700/60">
            {props.icon}
          </div>
          <div>
            <h3 className={`text-sm font-semibold leading-snug ${titleCls}`}>{props.title}</h3>
            <p className="mt-0.5 text-xs text-zinc-500">{props.subtitle}</p>
          </div>
        </div>
        <TrendGlyph
          trend={props.trend}
          title={props.trendTitle}
          variant={props.trendVariant ?? 'reverse'}
        />
      </div>
      <div className={`font-mono text-3xl font-bold tabular-nums tracking-tight ${z.value}`}>
        {props.valueDisplay}
      </div>
      {props.valueCaption ? (
        <p className="mt-2 text-xs leading-relaxed text-zinc-400">{props.valueCaption}</p>
      ) : null}
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <span className={`rounded-full border px-2.5 py-0.5 text-[11px] font-medium ${z.badge}`}>
          الهدف: {props.targetLabel}
        </span>
      </div>
    </div>
  );
}

function formatPct(n: number): string {
  if (!Number.isFinite(n)) return '—';
  return `${n.toFixed(2)}٪`;
}

function formatDays(n: number): string {
  if (!Number.isFinite(n) || n === Number.POSITIVE_INFINITY) return '—';
  return `${n.toFixed(2)} يوم`;
}

function formatIndex(n: number): string {
  if (!Number.isFinite(n) || n === Number.POSITIVE_INFINITY) return '—';
  return n.toFixed(2);
}

export function ControlTowerStatistics({
  statsData,
  mixedApiOrders,
  className = '',
}: ControlTowerStatisticsProps) {
  const stagnation = agentStagnationRate(statsData);
  const aging = pendingReturnsAgingDays(statsData);
  const backlog = backlogDensityIndex(statsData);

  const prev = statsData.previous;

  const stagnationTrend = reverseTrendDelta(
    stagnation,
    prev ? prevAgentStagnationRate(prev) : null,
  );
  const agingTrend = reverseTrendDelta(aging, prev ? prevPendingReturnsAgingDays(prev) : null);
  const backlogTrend = reverseTrendDelta(backlog, prev ? prevBacklogDensity(prev) : null);

  const zStag = reverseKpiZone(stagnation, 5);
  const zAging = reverseKpiZone(aging, 1.5);
  const zBack = reverseKpiZone(backlog, 0.5);

  const useMixedReturns = mixedApiOrders !== undefined;
  const eff = useMixedReturns ? computeReturnDischargeEfficiency(mixedApiOrders) : null;
  const dischargeScore = eff?.performanceScore ?? 0;
  const dischargeTrend = forwardTrendDelta(
    dischargeScore,
    prev?.returnDischargePerformanceScore != null &&
      Number.isFinite(prev.returnDischargePerformanceScore)
      ? prev.returnDischargePerformanceScore
      : null,
  );
  const zDischarge = efficiencyScoreZone(dischargeScore);

  const stagnationTrendTitle =
    stagnationTrend === 'up'
      ? 'ارتفع عن الأمس — يحتاج انتباهاً'
      : stagnationTrend === 'down'
        ? 'انخفض عن الأمس — اتجاه إيجابي'
        : 'مستقر مقارنة بالـ 24 ساعة الماضية';

  const agingTrendTitle =
    agingTrend === 'up'
      ? 'ارتفع عن الأمس — يحتاج انتباهاً'
      : agingTrend === 'down'
        ? 'انخفض عن الأمس — اتجاه إيجابي'
        : 'مستقر مقارنة بالـ 24 ساعة الماضية';

  const dischargeTrendTitle =
    dischargeTrend === 'up'
      ? 'تحسّنت الكفاءة عن القراءة السابقة'
      : dischargeTrend === 'down'
        ? 'انخفضت الكفاءة عن القراءة السابقة'
        : 'مستقر مقارنة بالقراءة السابقة';

  const backlogTrendTitle =
    backlogTrend === 'up'
      ? 'ارتفع عن الأمس — يحتاج انتباهاً'
      : backlogTrend === 'down'
        ? 'انخفض عن الأمس — اتجاه إيجابي'
        : 'مستقر مقارنة بالـ 24 ساعة الماضية';

  return (
    <section
      dir="rtl"
      lang="ar"
      className={`font-sans text-zinc-100 ${className}`}
      aria-labelledby="control-tower-heading"
    >
      <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="h-7 w-1 rounded-full bg-gradient-to-b from-violet-500 to-sky-500" />
          <div>
            <h2 id="control-tower-heading" className="text-lg font-bold tracking-tight text-white">
              إحصائيات برج المراقبة
            </h2>
            <p className="text-xs text-zinc-500">
              مؤشرات عكسية — كلما انخفض الرقم كان الأداء أفضل (مقارنة آخر 24 ساعة)
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <MetricCard
          title="معدل ركود المناديب"
          subtitle="طلبات مع مندوب أكثر من 96 ساعة ÷ إجمالي الطلبات مع مناديب"
          targetLabel="أقل من ٥٪"
          valueDisplay={formatPct(stagnation)}
          zone={zStag}
          trend={stagnationTrend}
          trendTitle={stagnationTrendTitle}
          icon={<Activity className="h-5 w-5 text-violet-400" aria-hidden />}
        />
        {useMixedReturns ? (
          <MetricCard
            title="كفاءة تفريغ المرتجعات مع المندوب"
            subtitle="يُحتسب من طلبات «مرتجع مع المندوب» فقط (دون «مع المندوب» العادية)"
            targetLabel="١٠٠٪ عند متوسط عمر ≤ ٣ أيام"
            valueDisplay={formatPct(dischargeScore)}
            zone={zDischarge}
            trend={dischargeTrend}
            trendTitle={dischargeTrendTitle}
            trendVariant="forward"
            dynamicTitleColor
            valueCaption={`معدل كفاءة التفريغ بناءً على متوسط عمر ${eff.averageAge.toFixed(2)} يوم`}
            icon={<Hourglass className="h-5 w-5 text-sky-400" aria-hidden />}
          />
        ) : (
          <MetricCard
            title="أعمار المرتجعات المعلقة"
            subtitle="إجمالي المرتجعات المعلقة ÷ متوسط المعالجة اليومية للمرتجعات"
            targetLabel="أقل من ١٫٥ يوم"
            valueDisplay={formatDays(aging)}
            zone={zAging}
            trend={agingTrend}
            trendTitle={agingTrendTitle}
            icon={<Hourglass className="h-5 w-5 text-sky-400" aria-hidden />}
          />
        )}
        <MetricCard
          title="مؤشر كثافة التراكم"
          subtitle="الطلبات المتأخرة الحالية ÷ متوسط الإنجاز اليومي"
          targetLabel="أقل من ٠٫٥"
          valueDisplay={formatIndex(backlog)}
          zone={zBack}
          trend={backlogTrend}
          trendTitle={backlogTrendTitle}
          icon={<Gauge className="h-5 w-5 text-amber-400" aria-hidden />}
        />
      </div>
    </section>
  );
}
