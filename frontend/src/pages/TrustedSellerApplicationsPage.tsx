import { Eye } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type RequestStatus = 'pending' | 'approved' | 'rejected' | 'draft';

type RequestRow = {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string | null;
  seller_type: string;
  operations_city: string | null;
  contact_phone: string | null;
  status: RequestStatus;
  created_at: string | null;
};

type RequestDetails = {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string | null;
  user_phone: string | null;
  user_is_trusted_seller: boolean;
  user_trusted_seller_verified_at: string | null;
  seller_type: string;
  is_non_student_confirmed: boolean;
  operations_city: string | null;
  primary_contact_name: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  category_id: number | null;
  offers_summary: string | null;
  estimated_ads_volume: string;
  preferred_student_contact_method: string;
  ack_review_discretion: boolean;
  ack_unitill_manages_directory: boolean;
  ack_no_app_access: boolean;
  status: RequestStatus;
  created_at: string | null;
  updated_at: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

const statusValues: RequestStatus[] = ['pending', 'approved', 'rejected', 'draft'];

/** API enum keys -> dictionary keys. */
const sellerTypeKey = {
  business: 'business',
  service_provider: 'service_provider',
  organization: 'organization_type',
} as const;

const contactMethodKey = {
  phone: 'phone',
  email: 'email',
  website: 'website',
} as const;

function statusSelectClass(status: RequestStatus) {
  if (status === 'approved') return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (status === 'pending' || status === 'draft') return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
  return 'border-rose-300/70 bg-rose-500/15 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-300';
}

export function TrustedSellerApplicationsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const didInitSearch = useRef(false);
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<RequestRow[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [statusSavingId, setStatusSavingId] = useState<number | null>(null);
  const [details, setDetails] = useState<RequestDetails | null>(null);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/trusted-seller-applications', {
        params: { page, per_page: pageSize, search: search || undefined },
      });
      const payload = ensureApiSuccess<PaginatedResponse<RequestRow>>(res, t.actionFailed);
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

  const openDetails = async (id: number) => {
    try {
      const res = await api.get(`/admin/trusted-seller-applications/${id}`);
      const payload = ensureApiSuccess<RequestDetails>(res, t.actionFailed);
      setDetails(payload || null);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    }
  };

  const updateStatus = async (row: RequestRow, status: RequestStatus) => {
    setStatusSavingId(row.id);
    try {
      const res = await api.put(`/admin/trusted-seller-applications/${row.id}`, { status });
      ensureApiSuccess(res, t.actionFailed);
      setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status } : r)));
      window.dispatchEvent(new Event('trusted-verifications-changed'));
      notify.success(t.statusUpdatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setStatusSavingId(null);
    }
  };

  const sellerTypeLabel = (value: string | null | undefined) => {
    const key = value ? sellerTypeKey[value as keyof typeof sellerTypeKey] : undefined;
    return key ? t[key] : value || '-';
  };
  const contactMethodLabel = (value: string | null | undefined) => {
    const key = value ? contactMethodKey[value as keyof typeof contactMethodKey] : undefined;
    return key ? t[key] : value || '-';
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.userVerifications}</h2>
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
          <Input className="h-9 w-[240px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.user}</th>
                  <th className="px-4 py-3 text-start">{t.sellerType}</th>
                  <th className="px-4 py-3 text-start">{t.city}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={6} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={6}>{t.noDataFound}</td></tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3">{row.user_name || '-'}</td>
                      <td className="px-4 py-3">{sellerTypeLabel(row.seller_type)}</td>
                      <td className="px-4 py-3">{row.operations_city || '-'}</td>
                      <td className="px-4 py-3">
                        <select
                          value={row.status}
                          disabled={statusSavingId === row.id}
                          onChange={(e) => updateStatus(row, e.target.value as RequestStatus)}
                          className={`h-9 min-w-[130px] rounded-full border px-3 text-xs font-semibold shadow-sm outline-none transition-all focus:ring-2 focus:ring-[#7367f0]/30 ${statusSelectClass(row.status)}`}
                        >
                          {statusValues.map((s) => (
                            <option key={s} value={s}>{t[s]}</option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-3">
                        <Button size="icon" variant="secondary" title={t.details} onClick={() => openDetails(row.id)}>
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

      {details && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-3xl">
            <CardContent className="space-y-4 p-5">
              <h3 className="text-lg font-semibold">{t.verificationRequest} #{details.id}</h3>
              <div className="grid gap-3 md:grid-cols-3">
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.user}</p>
                  <p className="mt-1 text-sm font-semibold">{details.user_name}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.email}</p>
                  <p className="mt-1 text-sm">{details.user_email || '-'}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.status}</p>
                  <p className="mt-1 text-sm">{t[details.status]}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.sellerType}</p>
                  <p className="mt-1 text-sm">{sellerTypeLabel(details.seller_type)}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.operationsCity}</p>
                  <p className="mt-1 text-sm">{details.operations_city || '-'}</p>
                </div>
                <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                  <p className="text-xs text-[#8a8da8]">{t.preferredContact}</p>
                  <p className="mt-1 text-sm">{contactMethodLabel(details.preferred_student_contact_method)}</p>
                </div>
              </div>
              <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                <p className="text-xs text-[#8a8da8]">{t.offersSummary}</p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{details.offers_summary || '-'}</p>
              </div>
              <div className="flex justify-end">
                <Button variant="secondary" onClick={() => setDetails(null)}>{t.cancel}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

