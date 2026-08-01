import { Eye } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type AdStatus = 'draft' | 'pending' | 'published' | 'rejected' | 'sold' | 'expired';
type AdRow = {
  id: number;
  public_id: string;
  title: string;
  status: AdStatus;
  cover_image: string | null;
  cover_image_url: string | null;
  user_id: number;
  user_name: string;
};
type PaginatedResponse<T> = { data: T[]; total: number };

function statusSelectClass(status: AdStatus) {
  if (status === 'published') return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (status === 'pending' || status === 'draft') return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
  return 'border-rose-300/70 bg-rose-500/15 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-300';
}

const statusValues: AdStatus[] = ['draft', 'pending', 'published', 'rejected', 'sold', 'expired'];

export function UserAdsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const navigate = useNavigate();
  const { userId } = useParams();
  const didInitSearch = useRef(false);
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<AdRow[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [statusSavingId, setStatusSavingId] = useState<number | null>(null);

  const backendOrigin = ((import.meta.env.VITE_BACKEND_ORIGIN as string | undefined) || window.location.origin).replace(/\/+$/, '');
  const uid = Number(userId || 0);

  const resolveImage = (row: AdRow) => {
    if (row.cover_image_url) return row.cover_image_url;
    if (!row.cover_image) return '';
    return `${backendOrigin}/storage/${String(row.cover_image).replace(/^\/+/, '')}`;
  };

  const fetchRows = async () => {
    if (!uid) return;
    setLoading(true);
    try {
      const res = await api.get('/admin/ads', { params: { user_id: uid, page, per_page: pageSize, search: search || undefined } });
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
  }, [uid, page, pageSize]);

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
  }, [search, uid]);

  const updateStatus = async (row: AdRow, status: AdStatus) => {
    setStatusSavingId(row.id);
    try {
      const res = await api.put(`/admin/ads/${row.id}`, { status });
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
  const ownerName = rows[0]?.user_name || `#${uid}`;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.userAds}</h2>
          <p className="text-sm text-[#8a8da8]">Owner: {ownerName}</p>
        </div>
        <div className="flex items-center gap-2">
          <Link to="/ads">
            <Button variant="secondary">{t.cancel}</Button>
          </Link>
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
          <Input className="h-9 w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
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
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={5} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={5}>{t.noDataFound}</td></tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">
                        {resolveImage(row) ? <img src={resolveImage(row)} alt="" className="h-10 w-10 rounded-lg object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" /> : <div className="h-10 w-10 rounded-lg bg-[#ececf3] dark:bg-[#44485f]" />}
                      </td>
                      <td className="px-4 py-3">{row.public_id || row.id}</td>
                      <td className="px-4 py-3">{row.title || '-'}</td>
                      <td className="px-4 py-3">
                        <select
                          value={row.status}
                          disabled={statusSavingId === row.id}
                          onChange={(e) => updateStatus(row, e.target.value as AdStatus)}
                          className={`h-9 min-w-[130px] rounded-full border px-3 text-xs font-semibold shadow-sm outline-none transition-all focus:ring-2 focus:ring-[#7367f0]/30 ${statusSelectClass(row.status)}`}
                        >
                          {statusValues.map((s) => <option key={s} value={s}>{t[s]}</option>)}
                        </select>
                      </td>
                      <td className="px-4 py-3">
                        <Button size="icon" variant="secondary" title={t.details} onClick={() => navigate(`/ads/${row.id}`)}>
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
            onPrev={() => setPage((p) => p - 1)}
            onNext={() => setPage((p) => p + 1)}
            prevDisabled={page <= 1}
            nextDisabled={page >= pagesCount}
          />
        </CardContent>
      </Card>
    </div>
  );
}
