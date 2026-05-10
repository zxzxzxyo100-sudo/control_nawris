import {
  ControlTowerStatistics,
  type ControlTowerStatsData,
  type MixedApiOrder,
} from './components/ControlTowerStatistics';

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
    returnDischargePerformanceScore: 82,
  },
};

/** بيانات تجريبية: خلط حالات؛ البطاقة تستخدم «مرتجع مع المندوب» فقط */
const demoMixedApi: MixedApiOrder[] = [
  { status: 'مع المندوب', updated_at: new Date(Date.now() - 9 * 86400000).toISOString() },
  {
    status: 'مرتجع مع المندوب',
    updated_at: new Date(Date.now() - 2 * 86400000).toISOString(),
  },
  {
    status: 'مرتجع مع المندوب',
    updated_at: new Date(Date.now() - 4 * 86400000).toISOString(),
  },
];

export default function App() {
  return (
    <div className="min-h-screen bg-[#09090b] px-4 py-10 text-zinc-100">
      <div className="mx-auto max-w-6xl">
        <ControlTowerStatistics statsData={demoStats} mixedApiOrders={demoMixedApi} />
      </div>
    </div>
  );
}
