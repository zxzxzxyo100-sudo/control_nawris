import { ControlTowerStatistics, type ControlTowerStatsData } from './components/ControlTowerStatistics';

/** Example payload aligned with ~3,500 delayed orders monitoring scenario */
const demoStats: ControlTowerStatsData = {
  ordersWithAgentOver96h: 142,
  totalOrdersWithAgents: 4100,
  totalPendingReturns: 312,
  avgDailyReturnProcessingVolume: 240,
  currentDelayedOrders: 3500,
  avgDailyCompletionRate: 8200,
  previous: {
    ordersWithAgentOver96h: 128,
    totalOrdersWithAgents: 4050,
    totalPendingReturns: 280,
    avgDailyReturnProcessingVolume: 235,
    currentDelayedOrders: 3380,
    avgDailyCompletionRate: 8100,
  },
};

export default function App() {
  return (
    <div className="min-h-screen bg-[#09090b] px-4 py-10 text-zinc-100">
      <div className="mx-auto max-w-6xl">
        <ControlTowerStatistics statsData={demoStats} />
      </div>
    </div>
  );
}
