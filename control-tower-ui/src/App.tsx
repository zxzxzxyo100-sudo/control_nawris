import { ControlTowerStatistics, type ControlTowerStatsData } from "./components/ControlTowerStatistics";

/** Sample payload — replace with API data in production */
const demoStats: ControlTowerStatsData = {
  delayedOrders: 3500,
  ordersWithAgents: 8200,
  returnOrders: 420,
  ordersWithAgentOver4Days: 310,
  avgDailyReturnProcessingVolume: 340,
  avgDailyCompletionRate: 9200,
  warehouseStock: { pct: 18.5, delayedCount: 37, totalCount: 200 },
  transitDelay: { pct: 9.2, delayedCount: 46, totalCount: 500 },
  kpi24hAgo: {
    agentStagnationRatePct: 4.2,
    pendingReturnsAgingDays: 1.35,
    backlogDensityIndex: 0.42,
    warehouseStockPct: 22.0,
    transitDelayPct: 11.0,
  },
};

export function App() {
  return (
    <div className="min-h-screen bg-slate-950 p-4 sm:p-8">
      <div className="mx-auto max-w-6xl space-y-6">
        <ControlTowerStatistics statsData={demoStats} />
      </div>
    </div>
  );
}
