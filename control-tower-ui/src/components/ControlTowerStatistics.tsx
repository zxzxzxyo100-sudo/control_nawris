import { useMemo } from "react";
import { Minus, TrendingDown, TrendingUp } from "lucide-react";

/**
 * Counts from CRM (delayed / with-agent / returns) plus optional
 * denominators for exact KPIs. When optional fields are omitted,
 * safe fallbacks avoid NaN; production APIs should send full numbers.
 */
export interface ControlTowerStatsData {
  delayedOrders: number;
  ordersWithAgents: number;
  returnOrders: number;
  /** Count of orders with same agent &gt; 4 days (96h) */
  ordersWithAgentOver4Days?: number;
  /**
   * @deprecated Use `ordersWithAgentOver4Days` (same field semantics must be &gt; 4 days).
   */
  ordersWithAgentOver48h?: number;
  avgDailyReturnProcessingVolume?: number;
  avgDailyCompletionRate?: number;
  /** Same three KPIs from ~24h ago for trend icons */
  kpi24hAgo?: {
    agentStagnationRatePct: number;
    pendingReturnsAgingDays: number;
    backlogDensityIndex: number;
  };
}

type Zone = "good" | "warn" | "danger";

const WARN_FACTOR = 1.25;

function zoneForReverseKpi(value: number, target: number): Zone {
  if (!Number.isFinite(value)) return "warn";
  if (value <= target) return "good";
  if (value <= target * WARN_FACTOR) return "warn";
  return "danger";
}

/** Agent stagnation %: green &lt;10%, yellow 10–20%, red &gt;20% (4-day window). */
function zoneForAgentStagnationRatePct(value: number): Zone {
  if (!Number.isFinite(value)) return "warn";
  if (value < 10) return "good";
  if (value <= 20) return "warn";
  return "danger";
}

function zoneClasses(zone: Zone): { ring: string; badge: string; value: string } {
  switch (zone) {
    case "good":
      return {
        ring: "ring-emerald-500/30 border-emerald-500/25",
        badge: "bg-emerald-500/15 text-emerald-300 border-emerald-500/25",
        value: "text-emerald-300",
      };
    case "warn":
      return {
        ring: "ring-amber-500/35 border-amber-500/30",
        badge: "bg-amber-500/12 text-amber-200 border-amber-500/25",
        value: "text-amber-200",
      };
    case "danger":
      return {
        ring: "ring-red-500/40 border-red-500/30",
        badge: "bg-red-500/12 text-red-300 border-red-500/25",
        value: "text-red-300",
      };
  }
}

type Trend = "up" | "down" | "flat";

/** For reverse KPIs: higher value = worse → "up" trend is bad for the business */
function trendReverse(
  current: number,
  previous: number | undefined
): Trend {
  if (previous === undefined || !Number.isFinite(previous) || !Number.isFinite(current)) {
    return "flat";
  }
  const eps = 1e-6;
  if (current > previous + eps) return "up";
  if (current < previous - eps) return "down";
  return "flat";
}

/** Reverse KPI: rising value = worse performance */
function TrendGlyph({ trend }: { trend: Trend }) {
  if (trend === "up") {
    return <TrendingUp className="h-4 w-4 shrink-0 text-red-400" aria-hidden />;
  }
  if (trend === "down") {
    return <TrendingDown className="h-4 w-4 shrink-0 text-emerald-400" aria-hidden />;
  }
  return <Minus className="h-4 w-4 shrink-0 text-slate-500" aria-hidden />;
}

function fmtPct(n: number): string {
  if (!Number.isFinite(n)) return "—";
  return `${n.toFixed(1)}٪`;
}

function fmtNum(n: number, digits: number): string {
  if (!Number.isFinite(n)) return "—";
  return n.toFixed(digits);
}

export interface ControlTowerStatisticsProps {
  statsData: ControlTowerStatsData;
  className?: string;
}

export function ControlTowerStatistics({
  statsData,
  className = "",
}: ControlTowerStatisticsProps) {
  const kpis = useMemo(() => {
    const {
      delayedOrders,
      ordersWithAgents,
      returnOrders,
      ordersWithAgentOver4Days,
      ordersWithAgentOver48h,
      avgDailyReturnProcessingVolume,
      avgDailyCompletionRate,
      kpi24hAgo,
    } = statsData;

    const stuckWithAgentOver4d =
      ordersWithAgentOver4Days ?? ordersWithAgentOver48h ?? 0;

    const denomAgents = Math.max(ordersWithAgents, 1);
    const agentStagnationRatePct =
      ordersWithAgents <= 0 ? 0 : (stuckWithAgentOver4d / denomAgents) * 100;

    const returnVol =
      avgDailyReturnProcessingVolume ??
      Math.max(returnOrders / 7, 1);
    const pendingReturnsAgingDays = returnVol > 0 ? returnOrders / returnVol : 0;

    const completionRate =
      avgDailyCompletionRate ?? Math.max(delayedOrders / 3.5, 1);
    const backlogDensityIndex =
      completionRate > 0 ? delayedOrders / completionRate : 0;

    return {
      agentStagnationRatePct,
      pendingReturnsAgingDays,
      backlogDensityIndex,
      kpi24hAgo,
    };
  }, [statsData]);

  const cards = useMemo(() => {
    const { agentStagnationRatePct, pendingReturnsAgingDays, backlogDensityIndex, kpi24hAgo } =
      kpis;

    const returnsAgingTarget = 1.5;
    const backlogTarget = 0.5;

    const stagnationSubtitle =
      "طلبات مع المندوب أكثر من ٤ أيام (٩٦ ساعة) ÷ إجمالي الطلبات مع مندوب × ١٠٠";
    const stagnationZonesHelp =
      "أخضر: أقل من ١٠٪ (طبيعي مع نافذة ٤ أيام) · كهرماني: ١٠٪–٢٠٪ · أحمر: أكثر من ٢٠٪ (ركود حرج)";

    return [
      {
        key: "stagnation",
        title: "معدل الركود (> 4 أيام)",
        subtitle: stagnationSubtitle,
        /** Full description + zone thresholds (tooltip) */
        subtitleTitle: `${stagnationSubtitle} — ${stagnationZonesHelp}`,
        value: fmtPct(agentStagnationRatePct),
        targetLabel: stagnationZonesHelp,
        zone: zoneForAgentStagnationRatePct(agentStagnationRatePct),
        trend: trendReverse(
          agentStagnationRatePct,
          kpi24hAgo?.agentStagnationRatePct
        ),
      },
      {
        key: "returns",
        title: "أعمار المرتجعات المعلّقة",
        subtitle: "إجمالي المرتجعات المعلّقة ÷ متوسط المعالجة اليومية للمرتجعات",
        value: `${fmtNum(pendingReturnsAgingDays, 2)} يوم`,
        targetLabel: `الهدف أقل من ${returnsAgingTarget} يوم`,
        target: returnsAgingTarget,
        zone: zoneForReverseKpi(pendingReturnsAgingDays, returnsAgingTarget),
        trend: trendReverse(
          pendingReturnsAgingDays,
          kpi24hAgo?.pendingReturnsAgingDays
        ),
      },
      {
        key: "backlog",
        title: "مؤشر كثافة التراكم",
        subtitle: "الطلبات المتأخرة الحالية ÷ متوسط الإنجاز اليومي",
        value: fmtNum(backlogDensityIndex, 2),
        targetLabel: `الهدف أقل من ${backlogTarget}`,
        target: backlogTarget,
        zone: zoneForReverseKpi(backlogDensityIndex, backlogTarget),
        trend: trendReverse(backlogDensityIndex, kpi24hAgo?.backlogDensityIndex),
      },
    ];
  }, [kpis]);

  return (
    <section
      dir="rtl"
      className={`rounded-2xl border border-violet-500/20 bg-gradient-to-br from-slate-900/95 via-slate-900 to-slate-950 p-5 shadow-cardDark sm:p-6 ${className}`}
    >
      <header className="mb-5 flex flex-col gap-1 border-b border-slate-700/60 pb-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-lg font-extrabold tracking-tight text-white sm:text-xl">
            إحصائيات برج المراقبة
          </h2>
          <p className="mt-1 max-w-2xl text-sm font-medium text-slate-400">
            مؤشرات أداء عكسية: كلما انخفض الرقم كان الأداء أفضل (مراقبة ~٣٥٠٠ طلب
            متأخر يومياً).
          </p>
        </div>
        <p className="font-mono text-xs text-slate-500">Reverse KPIs · 24h trend</p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {cards.map((c) => {
          const styles = zoneClasses(c.zone);
          return (
            <article
              key={c.key}
              className={`relative flex flex-col rounded-xl border bg-slate-900/80 p-4 shadow-md ring-1 ring-inset backdrop-blur-sm transition hover:border-slate-600/80 ${styles.ring}`}
            >
              <div className="mb-3 flex items-start justify-between gap-2">
                <h3 className="text-sm font-bold leading-snug text-slate-100">{c.title}</h3>
                <span
                  className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${styles.badge}`}
                  title={
                    c.key === "stagnation"
                      ? "مقارنة آخر ٢٤ ساعة — الركود: طلبات مع المندوب أكثر من ٤ أيام (٩٦ ساعة) ÷ إجمالي الطلبات مع مندوب"
                      : "مقارنة آخر ٢٤ ساعة"
                  }
                >
                  <TrendGlyph trend={c.trend} />
                  <span className="sr-only">اتجاه المؤشر</span>
                </span>
              </div>
              <p
                className="mb-4 line-clamp-2 text-xs leading-relaxed text-slate-500"
                title={
                  "subtitleTitle" in c && c.subtitleTitle
                    ? c.subtitleTitle
                    : `${c.subtitle}${c.target != null ? ` — الهدف: ≤ ${c.target}` : ""}`
                }
              >
                {c.subtitle}
              </p>
              <p
                className={`font-mono text-3xl font-semibold tabular-nums sm:text-4xl ${styles.value}`}
              >
                {c.value}
              </p>
              <p className="mt-3 text-xs font-medium text-slate-500">{c.targetLabel}</p>
            </article>
          );
        })}
      </div>
    </section>
  );
}
