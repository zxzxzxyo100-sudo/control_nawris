import { useCallback, useEffect, useState } from 'react';

/**
 * Conceptual backoffice / ops component: loads delayed orders that are still
 * assigned to captains. Uses a simple local list state (no global store) to
 * stay easy to reason about.
 *
 * Env (Vite): VITE_API_BASE_URL=https://your-domain.test/api
 *             VITE_EXTERNAL_API_TOKEN=*** (only for trusted internal tools — prefer server proxy in production)
 */
const defaultHeaders = (token) => ({
  Accept: 'application/json',
  'X-API-TOKEN': token,
});

export function DelayedOrdersWithCaptainTable({
  delayDays = 5,
  apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? '',
  apiToken = import.meta.env.VITE_EXTERNAL_API_TOKEN ?? '',
}) {
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);

    const url = new URL(
      'external-api/orders/delayed/with-captain',
      apiBaseUrl.endsWith('/') ? apiBaseUrl : `${apiBaseUrl}/`,
    );
    url.searchParams.set('delay_days', String(delayDays));

    try {
      const res = await fetch(url.toString(), {
        method: 'GET',
        headers: defaultHeaders(apiToken),
        credentials: 'omit',
      });

      const body = await res.json().catch(() => null);

      if (!res.ok) {
        throw new Error(body?.message ?? `Request failed (${res.status})`);
      }

      // API returns { success, meta, data: [...] } — keep the list in component state only.
      setRows(Array.isArray(body?.data) ? body.data : []);
      setMeta(body?.meta ?? null);
    } catch (e) {
      setRows([]);
      setMeta(null);
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [apiBaseUrl, apiToken, delayDays]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <header className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold tracking-tight text-slate-900">
            Delayed orders (with captain)
          </h1>
          <p className="text-sm text-slate-600">
            SLA breaches where a courier still holds the shipment — escalate or reassign from here.
          </p>
        </div>
        <div className="flex items-center gap-3">
          {meta ? (
            <p className="text-sm text-slate-500">
              Threshold:{' '}
              <span className="font-medium text-slate-800">{meta.delay_days_threshold}d</span>
              <span className="mx-2">·</span>
              <span className="font-medium text-slate-800">{meta.count}</span> orders
            </p>
          ) : null}
          <button
            type="button"
            onClick={() => void load()}
            className="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            disabled={loading}
          >
            {loading ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      </header>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
            <tr>
              <th className="px-4 py-3">Reference</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Captain</th>
              <th className="px-4 py-3">Promise</th>
              <th className="px-4 py-3 text-right">Delay (days)</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 text-slate-800">
            {rows.length === 0 && !loading ? (
              <tr>
                <td className="px-4 py-6 text-center text-slate-500" colSpan={5}>
                  No delayed orders match this threshold.
                </td>
              </tr>
            ) : null}

            {rows.map((row) => (
              <tr key={row.id} className="hover:bg-slate-50/80">
                <td className="px-4 py-3 font-medium">{row.reference}</td>
                <td className="px-4 py-3">
                  <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-200">
                    {row.status}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-col">
                    <span className="font-medium">{row.captain?.full_name ?? '—'}</span>
                    <span className="text-xs text-slate-500">{row.captain?.code ?? ''}</span>
                  </div>
                </td>
                <td className="px-4 py-3 text-slate-600">
                  {row.promised_delivery_at
                    ? new Date(row.promised_delivery_at).toLocaleString()
                    : '—'}
                </td>
                <td className="px-4 py-3 text-right font-semibold text-red-700">{row.delay_days}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default DelayedOrdersWithCaptainTable;
