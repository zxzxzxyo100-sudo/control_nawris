import {
  ControlTowerStatistics,
  type ControlTowerStatsData,
  type MixedApiOrder,
} from './components/ControlTowerStatistics';
import { EmployeeKPIs, type EmployeeData } from './components/EmployeeKPIs';

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

const demoEmployees: EmployeeData[] = [
  {
    id: 'emp-1',
    name: 'أحمد محمد السالم',
    totalAssigned: 120,
    completedOrders: 108,
    returnedOrders: 8,
    totalDeliveryDays: 195,
    previous: { completedOrders: 98, totalAssigned: 115, returnedOrders: 10, avgDeliveryDays: 1.9 },
  },
  {
    id: 'emp-2',
    name: 'خالد عبدالله العمري',
    totalAssigned: 95,
    completedOrders: 71,
    returnedOrders: 18,
    totalDeliveryDays: 284,
    previous: { completedOrders: 68, totalAssigned: 90, returnedOrders: 16, avgDeliveryDays: 3.8 },
  },
  {
    id: 'emp-3',
    name: 'فيصل ناصر الشمري',
    totalAssigned: 140,
    completedOrders: 126,
    returnedOrders: 11,
    totalDeliveryDays: 214,
    previous: { completedOrders: 118, totalAssigned: 135, returnedOrders: 13, avgDeliveryDays: 1.7 },
  },
  {
    id: 'emp-4',
    name: 'سلطان راشد القحطاني',
    totalAssigned: 80,
    completedOrders: 44,
    returnedOrders: 22,
    totalDeliveryDays: 220,
    previous: { completedOrders: 50, totalAssigned: 80, returnedOrders: 18, avgDeliveryDays: 4.2 },
  },
  {
    id: 'emp-5',
    name: 'ماجد عمر الزهراني',
    totalAssigned: 110,
    completedOrders: 97,
    returnedOrders: 9,
    totalDeliveryDays: 174,
    previous: { completedOrders: 90, totalAssigned: 105, returnedOrders: 11, avgDeliveryDays: 1.9 },
  },
  {
    id: 'emp-6',
    name: 'نواف سعد العتيبي',
    totalAssigned: 65,
    completedOrders: 38,
    returnedOrders: 20,
    totalDeliveryDays: 228,
    previous: { completedOrders: 42, totalAssigned: 65, returnedOrders: 17, avgDeliveryDays: 5.1 },
  },
];

export default function App() {
  return (
    <div className="min-h-screen bg-[#09090b] px-4 py-10 text-zinc-100">
      <div className="mx-auto max-w-6xl space-y-12">
        <ControlTowerStatistics statsData={demoStats} mixedApiOrders={demoMixedApi} />
        <EmployeeKPIs employees={demoEmployees} />
      </div>
    </div>
  );
}
