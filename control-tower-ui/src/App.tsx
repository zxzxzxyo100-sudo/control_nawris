import { ControlTowerStatistics, type ControlTowerStatsData } from "./components/ControlTowerStatistics";

/** Sample payload — replace with API data in production */
const demoStats: ControlTowerStatsData = {
  delayedOrders: 3500,
  ordersWithAgents: 8200,
  returnOrders: 420,
  ordersWithAgentOver48h: 310,
  avgDailyReturnProcessingVolume: 340,
  avgDailyCompletionRate: 9200,
  kpi24hAgo: {
    agentStagnationRatePct: 4.2,
    pendingReturnsAgingDays: 1.35,
    backlogDensityIndex: 0.42,
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
