import { useEffect, useState } from "react";
import { ControlTowerStatistics, type ControlTowerStatsData } from "./components/ControlTowerStatistics";

/** Sample payload — merged with MySQL KPIs when `control_tower_kpis.php` is reachable */
const demoStats: ControlTowerStatsData = {
  delayedOrders: 3500,
  ordersWithAgents: 8200,
  returnOrders: 420,
  ordersWithAgentOver4Days: 310,
  avgDailyReturnProcessingVolume: 340,
  avgDailyCompletionRate: 9200,
  warehouseStock: { pct: 18.5, delayedCount: 37, totalCount: 200 },
  transferDelays: { pct: 8.0, delayedCount: 24, totalCount: 300 },
  kpi24hAgo: {
    agentStagnationRatePct: 4.2,
    pendingReturnsAgingDays: 1.35,
    backlogDensityIndex: 0.42,
    warehouseStockPct: 22.0,
    transferDelayPct: 10.0,
  },
};

function resolveKpiUrl(): string {
  const raw = (import.meta.env.VITE_LOG_API_BASE as string | undefined)?.trim();
  const path = "control_tower_kpis.php";
  if (!raw) return `/api/${path}`;
  const base = raw.replace(/\/$/, "");
  if (base.startsWith("http://") || base.startsWith("https://")) {
    return `${base}/${path}`;
  }
  try {
    return new URL(`${base.replace(/^\//, "")}/${path}`, window.location.href).toString();
  } catch {
    return `/api/${path}`;
  }
}

export function App() {
  const [stats, setStats] = useState<ControlTowerStatsData>(demoStats);

  useEffect(() => {
    const url = resolveKpiUrl();
    let same = true;
    try {
      same = new URL(url, window.location.href).origin === window.location.origin;
    } catch {
      same = true;
    }
    fetch(url, {
      credentials: same ? "same-origin" : "omit",
      mode: "cors",
      headers: { Accept: "application/json" },
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((j: {
        ok?: boolean;
        warehouse?: { pct: number; delayed_count: number; total_count: number };
        transfer_delays?: { pct: number; delayed_count: number; total_count: number };
        transit?: { pct: number; delayed_count: number; total_count: number };
      }) => {
        if (!j?.ok) return;
        setStats((prev) => {
          const next = { ...prev };
          if (j.warehouse) {
            next.warehouseStock = {
              pct: Number(j.warehouse.pct) || 0,
              delayedCount: Number(j.warehouse.delayed_count) || 0,
              totalCount: Number(j.warehouse.total_count) || 0,
            };
          }
          const td = j.transfer_delays ?? j.transit;
          if (td && td.total_count != null) {
            next.transferDelays = {
              pct: Number(td.pct) || 0,
              delayedCount: Number(td.delayed_count) || 0,
              totalCount: Number(td.total_count) || 0,
            };
          }
          return next;
        });
      })
      .catch(() => {
        /* keep demoStats */
      });
  }, []);

  return (
    <div className="min-h-screen bg-slate-950 p-4 sm:p-8">
      <div className="mx-auto max-w-6xl space-y-6">
        <ControlTowerStatistics statsData={stats} />
      </div>
    </div>
  );
}
