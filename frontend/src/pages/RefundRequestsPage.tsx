import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type RefundStatus = 'requested' | 'refunded' | 'declined';

type Row = {
  id: number;
  public_id: string | null;
  title: string | null;
  user_id: number | null;
  user_name: string;
  user_email: string | null;
  listing_fee: string | null;
  currency: string | null;
  refund_status: RefundStatus;
  refund_requested_at: string | null;
  refund_request_reason: string | null;
  refund_reason: string | null;
  refunded_at: string | null;
  refund_declined_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

function statusBadgeClass(status: RefundStatus) {
  if (status === 'refunded') return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (status === 'declined') return 'border-rose-300/70 bg-rose-500/15 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-300';
  return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
}

export function RefundRequestsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const didInit = useRef(false);
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(false);
  const [statusFilter, setStatusFilter] = useState<'' | RefundStatus>('requested');
  const [page, setPage] = useState(1);
  const [pageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [decisionFor, setDecisionFor] = useState<{ row: Row; mode: 'accept' | 'decline' } | null>(null);
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/refund-requests', {
        params: { page, per_page: pageSize, status: statusFilter || undefined },
      });
      const payload = ensureApiSuccess<PaginatedResponse<Row>>(res, t.actionFailed);
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, pageSize, statusFilter]);

  useEffect(() => {
    if (!didInit.current) {
      didInit.current = true;
      return;
    }
    setPage(1);
  }, [statusFilter]);

  const submitDecision = async () => {
    if (!decisionFor || !reason.trim()) return;
    setSaving(true);
    try {
      const endpoint = decisionFor.mode === 'accept'
        ? `/admin/ads/${decisionFor.row.id}/refund`
        : `/admin/ads/${decisionFor.row.id}/refund/decline`;
      const res = await api.post(endpoint, { reason: reason.trim() });
      ensureApiSuccess(res, t.actionFailed);
      setDecisionFor(null);
      setReason('');
      await fetchRows();
      notify.success(decisionFor.mode === 'accept' ? t.refundIssued : t.refundDeclinedNotice);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.refundRequests}</h2>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as '' | RefundStatus)}
          className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
        >
          <option value="requested">{t.refundRequested}</option>
          <option value="refunded">{t.refunded}</option>
          <option value="declined">{t.refundDeclined}</option>
          <option value="">{t.allStatuses}</option>
        </select>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.ad}</th>
                  <th className="px-4 py-3 text-start">{t.user}</th>
                  <th className="px-4 py-3 text-start">{t.listingFee}</th>
                  <th className="px-4 py-3 text-start">{t.refundReason}</th>
                  <th className="px-4 py-3 text-start">{t.refundStatus}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={6} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={6}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">
                      <Link to={`/ads/${row.id}`} className="text-[#7367f0] hover:underline">
                        {row.title || `#${row.id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <div>{row.user_name}</div>
                      <div className="text-xs text-[#8a8da8]">{row.user_email}</div>
                    </td>
                    <td className="px-4 py-3">{row.listing_fee ? `£${row.listing_fee}` : '-'}</td>
                    <td className="px-4 py-3 max-w-[280px] whitespace-pre-wrap break-words">
                      {row.refund_status === 'requested' ? row.refund_request_reason : row.refund_reason || row.refund_request_reason || '-'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${statusBadgeClass(row.refund_status)}`}>
                        {row.refund_status === 'refunded' ? t.refunded : row.refund_status === 'declined' ? t.refundDeclined : t.refundRequested}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      {row.refund_status === 'requested' ? (
                        <div className="flex gap-2">
                          <Button size="sm" variant="destructive" onClick={() => { setDecisionFor({ row, mode: 'accept' }); setReason(''); }}>
                            {t.issueRefund}
                          </Button>
                          <Button size="sm" variant="secondary" onClick={() => { setDecisionFor({ row, mode: 'decline' }); setReason(''); }}>
                            {t.declineRefund}
                          </Button>
                        </div>
                      ) : (
                        <span className="text-xs text-[#8a8da8]">
                          {row.refund_status === 'refunded' ? row.refunded_at : row.refund_declined_at}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <TableFooter page={page} pageSize={pageSize} total={total} onPrev={() => setPage((p) => p - 1)} onNext={() => setPage((p) => p + 1)} prevDisabled={page <= 1} nextDisabled={page >= pagesCount} />
        </CardContent>
      </Card>

      {decisionFor && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-md">
            <CardHeader><CardTitle>{decisionFor.mode === 'accept' ? t.confirmRefund : t.confirmDeclineRefund}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <p className="text-sm">{decisionFor.mode === 'accept' ? t.refundConfirmation : t.declineRefundConfirmation}</p>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t.refundReasonPlaceholder}
                className="min-h-[90px] w-full rounded-xl border border-[#dbdbe8] bg-white px-3 py-2 text-sm text-[#2f2b3d] outline-none focus:ring-2 focus:ring-[#7367f0]/40 dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
              />
              <p className="text-xs text-[#8a8da8]">{t.userNotifiedNote}</p>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setDecisionFor(null)}>{t.cancel}</Button>
                <Button variant="destructive" onClick={submitDecision} disabled={saving || !reason.trim()}>
                  {decisionFor.mode === 'accept' ? t.issueRefund : t.declineRefund}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
