import { Eye } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type FavoriteAdRow = {
  id: number;
  public_id: string;
  title: string;
  status: string;
  price: string | null;
  currency: string | null;
  cover_image: string | null;
  cover_image_url: string | null;
  owner_name: string;
  created_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function UserFavoritesPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const navigate = useNavigate();
  const { userId } = useParams();
  const [rows, setRows] = useState<FavoriteAdRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);

  const uid = Number(userId || 0);
  const backendOrigin = ((import.meta.env.VITE_BACKEND_ORIGIN as string | undefined) || window.location.origin).replace(/\/+$/, '');

  const resolveImage = (row: FavoriteAdRow) => {
    if (row.cover_image_url) return row.cover_image_url;
    if (!row.cover_image) return '';
    return `${backendOrigin}/storage/${String(row.cover_image).replace(/^\/+/, '')}`;
  };

  const fetchRows = async () => {
    if (!uid) return;
    setLoading(true);
    try {
      const res = await api.get(`/admin/users/${uid}/favorites`, { params: { page, per_page: pageSize } });
      const payload = ensureApiSuccess<PaginatedResponse<FavoriteAdRow>>(res, 'Failed to load favorite ads');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load favorite ads.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uid, page, pageSize]);

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">User Favorite Ads</h2>
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
              <option key={size} value={size}>{size}</option>
            ))}
          </select>
          <Link to="/users">
            <Button variant="secondary">Back</Button>
          </Link>
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
                  <th className="px-4 py-3 text-start">{t.owner}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.price}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={7}>No favorite ads found.</td></tr>
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
                      <td className="px-4 py-3">{row.owner_name || '-'}</td>
                      <td className="px-4 py-3 capitalize">{row.status || '-'}</td>
                      <td className="px-4 py-3">{row.price ? `${row.price} ${row.currency || ''}`.trim() : '-'}</td>
                      <td className="px-4 py-3">
                        <Button size="icon" variant="secondary" title="Details" onClick={() => navigate(`/ads/${row.id}`)}>
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
