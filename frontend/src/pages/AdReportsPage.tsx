import { Eye, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type ReportStatus = 'pending' | 'reviewed' | 'dismissed';
type ReportPriority = 'normal' | 'urgent' | 'critical';

function priorityLabel(priority: string | null | undefined, t: ReturnType<typeof useI18n>['t']): string {
  if (priority === 'critical') return t.priorityCritical;
  if (priority === 'urgent') return t.priorityUrgent;
  return t.priorityNormal;
}

type ReportedAd = { id: number; public_id: string | null; title: string | null; status: string };
type Reporter = { id: number; name: string; email: string | null };

type ReportRow = {
  id: number;
  reason: string;
  reason_label: string | null;
  comment: string | null;
  status: ReportStatus;
  priority: ReportPriority;
  decision_reason: string | null;
  resolved_at: string | null;
  created_at: string | null;
  ad: ReportedAd | null;
  reporter: Reporter | null;
};

type ReportDetails = ReportRow & {
  ad_description: string | null;
  ad_owner: Reporter | null;
};

type Counts = { pending: number; reviewed: number; dismissed: number; total: number };
type ReasonOption = { value: string; label: string };

type ReportsResponse = {
  reports: { data: ReportRow[]; total: number };
  counts: Counts;
  reasons: ReasonOption[];
};

const statusValues: ReportStatus[] = ['pending', 'reviewed', 'dismissed'];

function statusSelectClass(status: ReportStatus) {
  if (status === 'reviewed') return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (status === 'pending') return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
  return 'border-slate-300/70 bg-slate-500/15 text-slate-700 dark:border-slate-500/40 dark:bg-slate-500/20 dark:text-slate-300';
}

export function AdReportsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const didInitSearch = useRef(false);
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<ReportRow[]>([]);
  const [counts, setCounts] = useState<Counts>({ pending: 0, reviewed: 0, dismissed: 0, total: 0 });
  const [reasons, setReasons] = useState<ReasonOption[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [reasonFilter, setReasonFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [statusSavingId, setStatusSavingId] = useState<number | null>(null);
  const [details, setDetails] = useState<ReportDetails | null>(null);
  const [action, setAction] = useState('warning');
  const [actionReason, setActionReason] = useState('');
  const [durationDays, setDurationDays] = useState('7');

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/ad-reports', {
        params: {
          page,
          per_page: pageSize,
          search: search || undefined,
          status: statusFilter || undefined,
          reason: reasonFilter || undefined,
          priority: priorityFilter || undefined,
        },
      });
      const payload = ensureApiSuccess<ReportsResponse>(res, t.actionFailed);
      setRows(payload?.reports?.data || []);
      setTotal(payload?.reports?.total || 0);
      if (payload?.counts) setCounts(payload.counts);
      if (payload?.reasons) setReasons(payload.reasons);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, pageSize, statusFilter, reasonFilter, priorityFilter]);

  useEffect(() => {
    if (!didInitSearch.current) {
      didInitSearch.current = true;
      return;
    }
    const timer = setTimeout(() => {
      setPage(1);
      fetchRows();
    }, 350);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const openDetails = async (id: number) => {
    try {
      const res = await api.get(`/admin/ad-reports/${id}`);
      const payload = ensureApiSuccess<ReportDetails>(res, t.actionFailed);
      setDetails(payload || null);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    }
  };

  const updateStatus = async (row: ReportRow, status: ReportStatus) => {
    const decisionReason = status === 'pending' ? null : window.prompt(t.reason);
    if (status !== 'pending' && !decisionReason?.trim()) return;
    const previous = row.status;
    setStatusSavingId(row.id);
    try {
      const res = await api.put(`/admin/ad-reports/${row.id}`, { status, decision_reason: decisionReason?.trim() || null });
      ensureApiSuccess(res, t.actionFailed);
      setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status } : r)));
      // العدّادات محسوبة على الخادم، لذلك تُحدَّث محلياً حتى الطلب التالي
      setCounts((prev) => ({
        ...prev,
        [previous]: Math.max(0, prev[previous] - 1),
        [status]: prev[status] + 1,
      }));
      notify.success(t.statusUpdatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setStatusSavingId(null);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  const applyModeration = async () => {
    if (!details?.ad_owner || !actionReason.trim()) return;
    const payload: Record<string, string | number> = { action, reason: actionReason.trim(), source_type: 'ad_report', source_id: details.id };
    if (action === 'temporary_suspension') payload.duration_days = Number(durationDays);
    try {
      await api.post(`/admin/users/${details.ad_owner.id}/moderation-actions`, payload);
      setActionReason('');
      notify.success(t.moderationActionRecorded);
    } catch (error) { notify.errorFrom(error, t.actionFailed); }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.adReports}</h2>
        <div className="flex flex-wrap items-center gap-2">
          <select value={priorityFilter} onChange={(e) => { setPage(1); setPriorityFilter(e.target.value); }} className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
            <option value="">{t.allPriorities}</option><option value="critical">{t.priorityCritical}</option><option value="urgent">{t.priorityUrgent}</option><option value="normal">{t.priorityNormal}</option>
          </select>
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value);
            }}
            className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm text-[#2f2b3d] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
          >
            <option value="">{t.allStatuses}</option>
            {statusValues.map((value) => (
              <option key={value} value={value}>{t[value]}</option>
            ))}
          </select>
          <select
            value={reasonFilter}
            onChange={(e) => {
              setPage(1);
              setReasonFilter(e.target.value);
            }}
            className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm text-[#2f2b3d] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
          >
            <option value="">{t.allReasons}</option>
            {reasons.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <select
            value={pageSize}
            onChange={(e) => {
              setPage(1);
              setPageSize(Number(e.target.value));
            }}
            className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm text-[#2f2b3d] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
          >
            {[10, 25, 50, 100].map((size) => (
              <option key={size} value={size}>{size}</option>
            ))}
          </select>
          <Input className="h-9 w-[240px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-4">
        {([
          [t.pending, counts.pending, 'text-amber-600 dark:text-amber-300'],
          [t.reviewed, counts.reviewed, 'text-emerald-600 dark:text-emerald-300'],
          [t.dismissed, counts.dismissed, 'text-slate-600 dark:text-slate-300'],
          [t.total, counts.total, 'text-[#7367f0]'],
        ] as const).map(([label, value, tone]) => (
          <Card key={label}>
            <CardContent className="p-4">
              <p className="text-xs text-[#8a8da8]">{label}</p>
              <p className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.ad}</th>
                  <th className="px-4 py-3 text-start">{t.reason}</th>
                  <th className="px-4 py-3 text-start">{t.priority}</th>
                  <th className="px-4 py-3 text-start">{t.reporter}</th>
                  <th className="px-4 py-3 text-start">{t.date}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={8} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={8}>{t.noDataFound}</td></tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3">
                        {row.ad ? (
                          <Link to={`/ads/${row.ad.id}`} className="font-medium text-[#7367f0] hover:underline">
                            {row.ad.title || `#${row.ad.public_id}`}
                          </Link>
                        ) : (
                          <span className="text-[#8a8da8]">{t.adDeleted}</span>
                        )}
                      </td>
                      <td className="px-4 py-3">{row.reason_label || row.reason}</td>
                      <td className="px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs font-semibold ${row.priority === 'critical' ? 'bg-rose-500/15 text-rose-600' : row.priority === 'urgent' ? 'bg-amber-500/15 text-amber-600' : 'bg-slate-500/15 text-slate-600'}`}>{priorityLabel(row.priority, t)}</span></td>
                      <td className="px-4 py-3">{row.reporter?.name || '-'}</td>
                      <td className="px-4 py-3 whitespace-nowrap">{row.created_at || '-'}</td>
                      <td className="px-4 py-3">
                        <select
                          value={row.status}
                          disabled={statusSavingId === row.id}
                          onChange={(e) => updateStatus(row, e.target.value as ReportStatus)}
                          className={`h-9 min-w-[130px] rounded-full border px-3 text-xs font-semibold shadow-sm outline-none transition-all focus:ring-2 focus:ring-[#7367f0]/30 ${statusSelectClass(row.status)}`}
                        >
                          {statusValues.map((value) => (
                            <option key={value} value={value}>{t[value]}</option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-3">
                        <Button variant="secondary" className="h-9 px-3" onClick={() => openDetails(row.id)}>
                          <Eye className="h-4 w-4" />
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          <TableFooter
            page={page}
            pageSize={pageSize}
            total={total}
            onPrev={() => setPage((p) => Math.max(1, p - 1))}
            onNext={() => setPage((p) => Math.min(pagesCount, p + 1))}
            prevDisabled={page <= 1}
            nextDisabled={page >= pagesCount}
          />
        </CardContent>
      </Card>

      {details ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setDetails(null)}>
          <Card className="max-h-[85vh] w-full max-w-2xl overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <CardContent className="space-y-4 p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="text-lg font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">Report #{details.id}</h3>
                  <p className="text-sm text-[#8a8da8]">{details.created_at || '-'}</p>
                </div>
                <Button variant="secondary" className="h-9 px-3" onClick={() => setDetails(null)}>
                  <X className="h-4 w-4" />
                </Button>
              </div>

              <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.reason}</p>
                  <p className="mt-1 text-sm font-semibold">{details.reason_label || details.reason}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.status}</p>
                  <p className="mt-1 text-sm font-semibold capitalize">{details.status}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.reporter}</p>
                  <p className="mt-1 text-sm">{details.reporter?.name || '-'}</p>
                  <p className="text-xs text-[#8a8da8]">{details.reporter?.email || ''}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.adOwner}</p>
                  <p className="mt-1 text-sm">{details.ad_owner?.name || '-'}</p>
                  <p className="text-xs text-[#8a8da8]">{details.ad_owner?.email || ''}</p>
                </div>
              </div>

              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.reporterExplanation}</p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{details.comment || '-'}</p>
              </div>

              {details.decision_reason ? <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.decisionReason}</p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{details.decision_reason}</p>
                <p className="mt-1 text-xs text-[#8a8da8]">{details.resolved_at || '-'}</p>
              </div> : null}

              {details.ad_owner ? <div className="space-y-3 rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-sm font-semibold">{t.moderationAction}</p>
                <div className="grid gap-2 sm:grid-cols-2">
                  <select value={action} onChange={(e) => setAction(e.target.value)} className="h-10 rounded-lg border bg-white px-3 text-sm dark:bg-[#2f3349]"><option value="warning">{t.modWarning}</option><option value="temporary_suspension">{t.modTemporarySuspension}</option><option value="permanent_suspension">{t.modPermanentSuspension}</option><option value="reactivated">{t.modReactivated}</option></select>
                  {action === 'temporary_suspension' ? <Input type="number" min="1" max="365" value={durationDays} onChange={(e) => setDurationDays(e.target.value)} placeholder={t.durationInDays} /> : null}
                </div>
                <textarea rows={3} value={actionReason} onChange={(e) => setActionReason(e.target.value)} placeholder={t.decisionReason} className="w-full rounded-lg border bg-white px-3 py-2 text-sm dark:bg-[#2f3349]" />
                <Button disabled={!actionReason.trim()} onClick={applyModeration}>{t.applyAction}</Button>
              </div> : null}

              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.reportedAd}</p>
                <p className="mt-1 text-sm font-semibold">{details.ad?.title || t.adDeleted}</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-[#6f6b7d] dark:text-[#b6b8cc]">
                  {details.ad_description || '-'}
                </p>
                {details.ad ? (
                  <Link to={`/ads/${details.ad.id}`} className="mt-2 inline-block text-sm font-semibold text-[#7367f0] hover:underline">
                    {t.openAd} →
                  </Link>
                ) : null}
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
