import { useMemo } from 'react';
import {
  Award,
  ChevronDown,
  ChevronUp,
  Minus,
  Package,
  RotateCcw,
  Timer,
  TrendingDown,
  TrendingUp,
  Users,
} from 'lucide-react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface EmployeePreviousStats {
  completedOrders?: number;
  totalAssigned?: number;
  returnedOrders?: number;
  avgDeliveryDays?: number;
}

export interface EmployeeData {
  id: string;
  /** اسم الموظف / المندوب */
  name: string;
  /** إجمالي الطلبات المعيّنة للمندوب في الفترة */
  totalAssigned: number;
  /** الطلبات المكتملة (تسليم ناجح) */
  completedOrders: number;
  /** الطلبات المرتجعة */
  returnedOrders: number;
  /** مجموع أيام التسليم للطلبات المكتملة (لحساب المتوسط) */
  totalDeliveryDays: number;
  /** بيانات الفترة السابقة لحساب الاتجاه */
  previous?: EmployeePreviousStats;
}

export interface EmployeeKPIsProps {
  employees: readonly EmployeeData[];
  className?: string;
}

// ---------------------------------------------------------------------------
// Derived metrics
// ---------------------------------------------------------------------------

interface EmployeeMetrics {
  completionRate: number;   // % — forward
  returnRate: number;       // % — reverse
  avgDeliveryDays: number;  // أيام — reverse
  overallScore: number;     // 0–100
}

function deriveMetrics(emp: EmployeeData): EmployeeMetrics {
  const completionRate =
    emp.totalAssigned > 0 ? (emp.completedOrders / emp.totalAssigned) * 100 : 0;

  const returnRate =
    emp.totalAssigned > 0 ? (emp.returnedOrders / emp.totalAssigned) * 100 : 0;

  const avgDeliveryDays =
    emp.completedOrders > 0 ? emp.totalDeliveryDays / emp.completedOrders : 0;

  // Weighted overall score (0–100)
  // completion: target 85% → 50 pts max
  const completionScore = Math.min(completionRate / 85, 1) * 50;
  // return rate: target < 10%, danger 25% → 30 pts max
  const returnScore = Math.max(0, 1 - returnRate / 25) * 30;
  // speed: target < 2 days, danger 5 days → 20 pts max
  const speedScore = Math.max(0, 1 - (avgDeliveryDays - 1) / 4) * 20;

  const overallScore = completionScore + returnScore + speedScore;

  return { completionRate, returnRate, avgDeliveryDays, overallScore };
}

// ---------------------------------------------------------------------------
// Zone helpers
// ---------------------------------------------------------------------------

type Zone = 'green' | 'yellow' | 'red';

function overallZone(score: number): Zone {
  if (score >= 75) return 'green';
  if (score >= 50) return 'yellow';
  return 'red';
}

const zoneRing: Record<Zone, string> = {
  green: 'ring-emerald-500/30',
  yellow: 'ring-amber-500/35',
  red: 'ring-rose-500/40',
};

const zoneBadge: Record<Zone, string> = {
  green: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
  yellow: 'bg-amber-500/15 text-amber-200 border-amber-500/35',
  red: 'bg-rose-500/15 text-rose-300 border-rose-500/35',
};

const zoneLabel: Record<Zone, string> = {
  green: 'متميز',
  yellow: 'مقبول',
  red: 'يحتاج تحسيناً',
};

const zonePanel: Record<Zone, string> = {
  green: 'from-emerald-500/8 via-zinc-900/80 to-zinc-950',
  yellow: 'from-amber-500/8 via-zinc-900/80 to-zinc-950',
  red: 'from-rose-500/10 via-zinc-900/80 to-zinc-950',
};

const zoneScore: Record<Zone, string> = {
  green: 'text-emerald-300',
  yellow: 'text-amber-200',
  red: 'text-rose-300',
};

// ---------------------------------------------------------------------------
// Trend helpers
// ---------------------------------------------------------------------------

type TrendDir = 'up' | 'down' | 'flat';

function trendDir(current: number, prev: number | undefined | null, eps = 0.5): TrendDir {
  if (prev == null || !Number.isFinite(prev) || !Number.isFinite(current)) return 'flat';
  if (current > prev + eps) return 'up';
  if (current < prev - eps) return 'down';
  return 'flat';
}

function TrendIcon({ dir, good }: { dir: TrendDir; good: boolean }) {
  if (dir === 'up') {
    return good ? (
      <TrendingUp className="h-3.5 w-3.5 text-emerald-400" />
    ) : (
      <TrendingUp className="h-3.5 w-3.5 text-rose-400" />
    );
  }
  if (dir === 'down') {
    return good ? (
      <TrendingDown className="h-3.5 w-3.5 text-rose-400" />
    ) : (
      <TrendingDown className="h-3.5 w-3.5 text-emerald-400" />
    );
  }
  return <Minus className="h-3.5 w-3.5 text-zinc-500" />;
}

// ---------------------------------------------------------------------------
// Stat pill inside each card
// ---------------------------------------------------------------------------

function StatPill({
  label,
  value,
  sub,
  trendDir: dir,
  trendGoodUp,
  icon,
}: {
  label: string;
  value: string;
  sub?: string;
  trendDir?: TrendDir;
  trendGoodUp?: boolean;
  icon: React.ReactNode;
}) {
  return (
    <div className="flex flex-col gap-0.5 rounded-xl bg-zinc-900/70 px-3 py-2.5 ring-1 ring-zinc-800/60">
      <div className="flex items-center gap-1.5 text-[11px] text-zinc-500">
        {icon}
        <span>{label}</span>
      </div>
      <div className="flex items-end gap-1.5">
        <span className="font-mono text-sm font-semibold tabular-nums text-zinc-100">{value}</span>
        {dir && dir !== 'flat' && trendGoodUp !== undefined && (
          <TrendIcon dir={dir} good={dir === 'up' ? trendGoodUp : !trendGoodUp} />
        )}
      </div>
      {sub && <span className="text-[10px] text-zinc-600">{sub}</span>}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Employee card
// ---------------------------------------------------------------------------

function EmployeeCard({
  emp,
  metrics,
  rank,
}: {
  emp: EmployeeData;
  metrics: EmployeeMetrics;
  rank: number;
}) {
  const zone = overallZone(metrics.overallScore);

  const prevCompRate =
    emp.previous?.totalAssigned && emp.previous.totalAssigned > 0
      ? ((emp.previous.completedOrders ?? 0) / emp.previous.totalAssigned) * 100
      : null;
  const prevRetRate =
    emp.previous?.totalAssigned && emp.previous.totalAssigned > 0
      ? ((emp.previous.returnedOrders ?? 0) / emp.previous.totalAssigned) * 100
      : null;
  const prevAvgDays = emp.previous?.avgDeliveryDays ?? null;

  const compTrend = trendDir(metrics.completionRate, prevCompRate);
  const retTrend = trendDir(metrics.returnRate, prevRetRate);
  const daysTrend = trendDir(metrics.avgDeliveryDays, prevAvgDays, 0.1);

  const scoreRounded = Math.round(metrics.overallScore);

  return (
    <div
      className={`relative overflow-hidden rounded-2xl border border-zinc-800/80 bg-gradient-to-br ${zonePanel[zone]} p-5 shadow-lg shadow-black/30 ring-1 ring-inset ${zoneRing[zone]}`}
    >
      {/* Header */}
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0">
          {/* Rank badge */}
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-zinc-900/80 ring-1 ring-zinc-700/60 font-mono text-sm font-bold text-zinc-300">
            #{rank}
          </div>
          <div className="min-w-0">
            <h3 className="truncate text-sm font-semibold text-zinc-100">{emp.name}</h3>
            <p className="text-[11px] text-zinc-500">{emp.totalAssigned} طلب معيّن</p>
          </div>
        </div>

        {/* Overall score */}
        <div className="flex flex-col items-end shrink-0">
          <span className={`font-mono text-2xl font-bold tabular-nums ${zoneScore[zone]}`}>
            {scoreRounded}
          </span>
          <span
            className={`mt-0.5 rounded-full border px-2 py-0.5 text-[10px] font-medium ${zoneBadge[zone]}`}
          >
            {zoneLabel[zone]}
          </span>
        </div>
      </div>

      {/* KPI pills */}
      <div className="grid grid-cols-3 gap-2">
        <StatPill
          label="معدل الإتمام"
          value={`${metrics.completionRate.toFixed(1)}٪`}
          sub="هدف ≥٨٥٪"
          trendDir={compTrend}
          trendGoodUp={true}
          icon={<Package className="h-3 w-3" />}
        />
        <StatPill
          label="معدل المرتجعات"
          value={`${metrics.returnRate.toFixed(1)}٪`}
          sub="هدف <١٠٪"
          trendDir={retTrend}
          trendGoodUp={false}
          icon={<RotateCcw className="h-3 w-3" />}
        />
        <StatPill
          label="متوسط أيام التسليم"
          value={`${metrics.avgDeliveryDays.toFixed(1)}`}
          sub="هدف <٢ يوم"
          trendDir={daysTrend}
          trendGoodUp={false}
          icon={<Timer className="h-3 w-3" />}
        />
      </div>

      {/* Progress bar */}
      <div className="mt-4">
        <div className="mb-1 flex items-center justify-between text-[10px] text-zinc-500">
          <span>الإنجاز الكلي</span>
          <span className={`font-mono font-semibold ${zoneScore[zone]}`}>{scoreRounded}/100</span>
        </div>
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-zinc-800/80">
          <div
            className={`h-full rounded-full transition-all ${
              zone === 'green'
                ? 'bg-emerald-500'
                : zone === 'yellow'
                  ? 'bg-amber-400'
                  : 'bg-rose-500'
            }`}
            style={{ width: `${Math.min(scoreRounded, 100)}%` }}
          />
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Summary bar at top
// ---------------------------------------------------------------------------

function SummaryBar({
  total,
  green,
  yellow,
  red,
  avgScore,
}: {
  total: number;
  green: number;
  yellow: number;
  red: number;
  avgScore: number;
}) {
  return (
    <div className="mb-5 flex flex-wrap gap-3">
      <div className="flex items-center gap-2 rounded-xl bg-zinc-900/70 px-3.5 py-2 ring-1 ring-zinc-800/60">
        <Users className="h-4 w-4 text-zinc-400" />
        <span className="text-xs text-zinc-400">الإجمالي</span>
        <span className="font-mono text-sm font-bold text-zinc-100">{total}</span>
      </div>
      <div className="flex items-center gap-2 rounded-xl bg-emerald-500/10 px-3.5 py-2 ring-1 ring-emerald-500/25">
        <ChevronUp className="h-4 w-4 text-emerald-400" />
        <span className="text-xs text-emerald-300">متميز</span>
        <span className="font-mono text-sm font-bold text-emerald-300">{green}</span>
      </div>
      <div className="flex items-center gap-2 rounded-xl bg-amber-500/10 px-3.5 py-2 ring-1 ring-amber-500/25">
        <Minus className="h-4 w-4 text-amber-300" />
        <span className="text-xs text-amber-200">مقبول</span>
        <span className="font-mono text-sm font-bold text-amber-200">{yellow}</span>
      </div>
      <div className="flex items-center gap-2 rounded-xl bg-rose-500/10 px-3.5 py-2 ring-1 ring-rose-500/25">
        <ChevronDown className="h-4 w-4 text-rose-400" />
        <span className="text-xs text-rose-300">يحتاج تحسيناً</span>
        <span className="font-mono text-sm font-bold text-rose-300">{red}</span>
      </div>
      <div className="flex items-center gap-2 rounded-xl bg-zinc-900/70 px-3.5 py-2 ring-1 ring-zinc-800/60">
        <Award className="h-4 w-4 text-violet-400" />
        <span className="text-xs text-zinc-400">متوسط الدرجات</span>
        <span className="font-mono text-sm font-bold text-violet-300">{avgScore.toFixed(1)}</span>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export function EmployeeKPIs({ employees, className = '' }: EmployeeKPIsProps) {
  const ranked = useMemo(() => {
    return employees
      .map((emp) => ({ emp, metrics: deriveMetrics(emp) }))
      .sort((a, b) => b.metrics.overallScore - a.metrics.overallScore);
  }, [employees]);

  const summary = useMemo(() => {
    let green = 0;
    let yellow = 0;
    let red = 0;
    let scoreSum = 0;
    for (const { metrics } of ranked) {
      const z = overallZone(metrics.overallScore);
      if (z === 'green') green++;
      else if (z === 'yellow') yellow++;
      else red++;
      scoreSum += metrics.overallScore;
    }
    return {
      total: ranked.length,
      green,
      yellow,
      red,
      avgScore: ranked.length > 0 ? scoreSum / ranked.length : 0,
    };
  }, [ranked]);

  if (employees.length === 0) {
    return (
      <section dir="rtl" lang="ar" className={`font-sans text-zinc-100 ${className}`}>
        <p className="text-sm text-zinc-500">لا توجد بيانات موظفين.</p>
      </section>
    );
  }

  return (
    <section
      dir="rtl"
      lang="ar"
      className={`font-sans text-zinc-100 ${className}`}
      aria-labelledby="employee-kpi-heading"
    >
      {/* Heading */}
      <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="h-7 w-1 rounded-full bg-gradient-to-b from-violet-500 to-emerald-500" />
          <div>
            <h2
              id="employee-kpi-heading"
              className="text-lg font-bold tracking-tight text-white"
            >
              مؤشرات أداء الموظفين
            </h2>
            <p className="text-xs text-zinc-500">
              مرتّب تنازلياً حسب الإنجاز الكلي — الهدف: إتمام ≥٨٥٪ · مرتجعات &lt;١٠٪ · تسليم &lt;٢ يوم
            </p>
          </div>
        </div>
      </div>

      {/* Summary bar */}
      <SummaryBar {...summary} />

      {/* Employee grid */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {ranked.map(({ emp, metrics }, i) => (
          <EmployeeCard key={emp.id} emp={emp} metrics={metrics} rank={i + 1} />
        ))}
      </div>
    </section>
  );
}
