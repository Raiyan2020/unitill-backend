import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';

type DeviceRow = {
  id: number;
  device_identifier: string | null;
  device_name: string | null;
  country_code: string | null;
  last_seen_at: string | null;
  is_active: boolean;
  created_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function UserDevicesPage() {
  const notify = useNotify();
  const { userId } = useParams();
  const [rows, setRows] = useState<DeviceRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);

  const uid = Number(userId || 0);

  const fetchRows = async () => {
    if (!uid) return;
    setLoading(true);
    try {
      const res = await api.get(`/admin/users/${uid}/devices`, { params: { page, per_page: pageSize } });
      const payload = ensureApiSuccess<PaginatedResponse<DeviceRow>>(res, 'Failed to load user devices');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load user devices.');
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
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">User Device Sessions</h2>
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
                  <th className="px-4 py-3 text-start">ID</th>
                  <th className="px-4 py-3 text-start">Device Name</th>
                  <th className="px-4 py-3 text-start">Identifier</th>
                  <th className="px-4 py-3 text-start">Country</th>
                  <th className="px-4 py-3 text-start">Last Seen</th>
                  <th className="px-4 py-3 text-start">Active</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={6} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={6}>No sessions found.</td></tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3">{row.device_name || '-'}</td>
                      <td className="px-4 py-3">{row.device_identifier || '-'}</td>
                      <td className="px-4 py-3">{row.country_code || '-'}</td>
                      <td className="px-4 py-3">{row.last_seen_at || '-'}</td>
                      <td className="px-4 py-3">{row.is_active ? 'Yes' : 'No'}</td>
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
