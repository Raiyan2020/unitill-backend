import { Eye, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api, backendOrigin } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type AdStatus = 'draft' | 'pending' | 'published' | 'rejected' | 'sold' | 'expired';

type AdRow = {
  id: number;
  public_id: string;
  title: string;
  status: AdStatus;
  payment_status: string | null;
  cover_image: string | null;
  cover_image_url: string | null;
  user_id: number;
  user_name: string;
  created_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

const statusValues: AdStatus[] = ['draft', 'pending', 'published', 'rejected', 'sold', 'expired'];

/** Payment states the API accepts as "settled" before an ad may go live. */
const SETTLED_PAYMENTS = ['paid', 'free', 'waived', 'coupon'];

/** The API refuses to publish an ad whose listing fee is still outstanding. */
function canPublish(row: AdRow): boolean {
  return row.status === 'published' || SETTLED_PAYMENTS.includes(row.payment_status ?? '');
}

function statusSelectClass(status: AdStatus) {
  if (status === 'published') return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (status === 'pending' || status === 'draft') return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
  return 'border-rose-300/70 bg-rose-500/15 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-300';
}

export function AdsPage() {
  const navigate = useNavigate();
  const { t } = useI18n();
  const notify = useNotify();
  const didInitSearch = useRef(false);
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<AdRow[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [deleting, setDeleting] = useState<AdRow | null>(null);
  const [savingDelete, setSavingDelete] = useState(false);
  const [deleteReason, setDeleteReason] = useState('');
  const [statusSavingId, setStatusSavingId] = useState<number | null>(null);

  const resolveImage = (row: AdRow) => {
    if (row.cover_image_url) return row.cover_image_url;
    if (!row.cover_image) return '';
    return `${backendOrigin}/storage/${String(row.cover_image).replace(/^\/+/, '')}`;
  };

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/ads', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload = ensureApiSuccess<PaginatedResponse<AdRow>>(res, t.actionFailed);
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
  }, [page, pageSize]);

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

  const deleteAd = async () => {
    if (!deleting) return;
    setSavingDelete(true);
    try {
      const res = await api.delete(`/admin/ads/${deleting.id}`, { data: { reason: deleteReason } });
      ensureApiSuccess(res, t.actionFailed);
      setDeleting(null);
      setDeleteReason('');
      await fetchRows();
      notify.success(t.deletedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setSavingDelete(false);
    }
  };

  const updateStatus = async (row: AdRow, status: AdStatus) => {
    if (status === row.status) return;
    const reason = window.prompt(t.reason);
    if (!reason?.trim()) return;
    setStatusSavingId(row.id);
    try {
      const res = await api.put(`/admin/ads/${row.id}`, { status, reason: reason.trim(), source_type: 'manual' });
      ensureApiSuccess(res, t.actionFailed);
      setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status } : r)));
      notify.success(t.statusUpdatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setStatusSavingId(null);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.ads}</h2>
        <div className="flex items-center gap-2">
          <select
            value={pageSize}
            onChange={(e) => {
              setPage(1);
              setPageSize(Number(e.target.value));
            }}
            className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm text-[#2f2b3d] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
          >
            {[10, 25, 50, 100].map((size) => (
              <option key={size} value={size}>
                {size}
              </option>
            ))}
          </select>
          <Input className="h-9 w-[240px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.image}</th>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.title}</th>
                  <th className="px-4 py-3 text-start">{t.user}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={7}>
                      {t.noDataFound}
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">
                        {resolveImage(row) ? (
                          <img src={resolveImage(row)} alt="" className="h-10 w-10 rounded-lg object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" />
                        ) : (
                          <div className="h-10 w-10 rounded-lg bg-[#ececf3] dark:bg-[#44485f]" />
                        )}
                      </td>
                      <td className="px-4 py-3">{row.public_id || row.id}</td>
                      <td className="px-4 py-3">{row.title || '-'}</td>
                      <td className="px-4 py-3">
                        <button
                          type="button"
                          className="text-[#7367f0] hover:underline"
                          onClick={() => navigate(`/ads/user/${row.user_id}`)}
                        >
                          {row.user_name || '-'}
                        </button>
                      </td>
                      <td className="px-4 py-3">
                        <select
                          value={row.status}
                          disabled={statusSavingId === row.id}
                          onChange={(e) => updateStatus(row, e.target.value as AdStatus)}
                          className={`h-9 min-w-[130px] rounded-full border px-3 text-xs font-semibold shadow-sm outline-none transition-all focus:ring-2 focus:ring-[#7367f0]/30 ${statusSelectClass(row.status)}`}
                        >
                          {statusValues.map((s) => (
                            <option key={s} value={s} disabled={s === 'published' && !canPublish(row)}>
                              {t[s]}
                              {s === 'published' && !canPublish(row) ? ` — ${t.paymentUnsettledShort}` : ''}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <Button size="icon" variant="secondary" title={t.details} onClick={() => navigate(`/ads/${row.id}`)}>
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button size="icon" variant="destructive" title={t.delete} onClick={() => setDeleting(row)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
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
            onPrev={() => setPage((p) => p - 1)}
            onNext={() => setPage((p) => p + 1)}
            prevDisabled={page <= 1}
            nextDisabled={page >= pagesCount}
          />
        </CardContent>
      </Card>

      {deleting && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-md">
            <CardContent className="pt-6">
              <p className="text-sm">{t.deleteAdConfirmation}</p>
              <p className="mb-4 mt-1 text-sm font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">
                {deleting.title || deleting.public_id || `#${deleting.id}`}
              </p>
              <textarea
                value={deleteReason}
                onChange={(event) => setDeleteReason(event.target.value)}
                placeholder={t.reason}
                className="mb-4 min-h-24 w-full rounded-lg border border-[#dbdbe8] bg-white p-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
              />
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => { setDeleting(null); setDeleteReason(''); }}>{t.cancel}</Button>
                <Button variant="destructive" onClick={deleteAd} disabled={savingDelete || !deleteReason.trim()}>{t.delete}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
