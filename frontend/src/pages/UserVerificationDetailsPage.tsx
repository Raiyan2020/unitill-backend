import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type RequestRow = {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string | null;
  seller_type: string;
  operations_city: string | null;
  contact_phone: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'draft';
  created_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function UserVerificationDetailsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const { userId } = useParams();
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<RequestRow[]>([]);

  const uid = Number(userId || 0);

  useEffect(() => {
    const fetchRows = async () => {
      if (!uid) return;
      setLoading(true);
      try {
        const res = await api.get('/admin/trusted-seller-applications', {
          params: { user_id: uid, per_page: 100 },
        });
        const payload = ensureApiSuccess<PaginatedResponse<RequestRow>>(res, t.actionFailed);
        setRows(payload?.data || []);
      } catch (error) {
        notify.errorFrom(error, t.actionFailed);
      } finally {
        setLoading(false);
      }
    };
    fetchRows();
  }, [uid]);

  const latest = rows[0] || null;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.userVerificationDetails}</h2>
        <div className="flex items-center gap-2">
          <Link to="/users"><Button variant="secondary">{t.backToUsers}</Button></Link>
          <Link to="/user-verifications"><Button variant="secondary">{t.allRequests}</Button></Link>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t.latestVerificationRequest}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-[#8a8da8]">{t.loading}</p>
          ) : !latest ? (
            <p className="text-sm text-[#8a8da8]">{t.noVerificationRequest}</p>
          ) : (
            <div className="grid gap-3 md:grid-cols-3">
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.user}</p>
                <p className="mt-1 text-sm font-semibold">{latest.user_name}</p>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.email}</p>
                <p className="mt-1 text-sm">{latest.user_email || '-'}</p>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.status}</p>
                <p className="mt-1 text-sm capitalize">{latest.status}</p>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.sellerType}</p>
                <p className="mt-1 text-sm">{latest.seller_type}</p>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.city}</p>
                <p className="mt-1 text-sm">{latest.operations_city || '-'}</p>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.phone}</p>
                <p className="mt-1 text-sm">{latest.contact_phone || '-'}</p>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {rows.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>{t.allUserVerificationRequests}</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                  <tr>
                    <th className="px-4 py-3 text-start">{t.requestNumber}</th>
                    <th className="px-4 py-3 text-start">{t.status}</th>
                    <th className="px-4 py-3 text-start">{t.sellerType}</th>
                    <th className="px-4 py-3 text-start">{t.createdAt}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3 capitalize">{row.status}</td>
                      <td className="px-4 py-3">{row.seller_type}</td>
                      <td className="px-4 py-3">{row.created_at || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}

