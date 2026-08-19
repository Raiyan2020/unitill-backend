import { Eye, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type CouponType = 'percentage' | 'fixed';

type CouponRow = {
  id: number;
  code: string;
  type: CouponType;
  value: number;
  value_label: string;
  max_discount: number | null;
  min_amount: number | null;
  max_redemptions: number | null;
  redemptions_count: number;
  starts_at: string | null;
  expires_at: string | null;
  is_active: boolean;
  is_expired: boolean;
  is_exhausted: boolean;
  created_at: string | null;
};

type Redemption = {
  id: number;
  user: { id: number; name: string; email: string | null } | null;
  ad: { id: number; title: string | null } | null;
  original_amount: number;
  discount_amount: number;
  final_amount: number;
  created_at: string | null;
};

type CouponDetails = CouponRow & { redemptions: Redemption[] };
type Counts = { total: number; active: number; expired: number; redemptions: number };
type CouponsResponse = { coupons: { data: CouponRow[]; total: number }; counts: Counts };

const emptyForm = {
  code: '',
  type: 'percentage' as CouponType,
  value: '',
  max_discount: '',
  min_amount: '',
  max_redemptions: '',
  starts_at: '',
  expires_at: '',
  is_active: true,
};

type FormState = typeof emptyForm;

function statusOf(row: CouponRow) {
  if (!row.is_active) return { key: 'inactive' as const, tone: 'bg-slate-500/15 text-slate-700 dark:text-slate-300' };
  if (row.is_expired) return { key: 'expired' as const, tone: 'bg-rose-500/15 text-rose-700 dark:text-rose-300' };
  if (row.is_exhausted) return { key: 'usedUp' as const, tone: 'bg-amber-500/15 text-amber-700 dark:text-amber-300' };
  return { key: 'active' as const, tone: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' };
}

export function CouponsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const didInitSearch = useRef(false);
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<CouponRow[]>([]);
  const [counts, setCounts] = useState<Counts>({ total: 0, active: 0, expired: 0, redemptions: 0 });
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [details, setDetails] = useState<CouponDetails | null>(null);
  const [form, setForm] = useState<FormState | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/coupons', {
        params: { page, per_page: pageSize, search: search || undefined, status: statusFilter || undefined },
      });
      const payload = ensureApiSuccess<CouponsResponse>(res, t.actionFailed);
      setRows(payload?.coupons?.data || []);
      setTotal(payload?.coupons?.total || 0);
      if (payload?.counts) setCounts(payload.counts);
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

  const openCreate = () => {
    setEditingId(null);
    setForm({ ...emptyForm });
  };

  const openEdit = (row: CouponRow) => {
    setEditingId(row.id);
    setForm({
      code: row.code,
      type: row.type,
      value: String(row.value),
      max_discount: row.max_discount != null ? String(row.max_discount) : '',
      min_amount: row.min_amount != null ? String(row.min_amount) : '',
      max_redemptions: row.max_redemptions != null ? String(row.max_redemptions) : '',
      starts_at: row.starts_at ? row.starts_at.slice(0, 10) : '',
      expires_at: row.expires_at ? row.expires_at.slice(0, 10) : '',
      is_active: row.is_active,
    });
  };

  const save = async () => {
    if (!form) return;
    setSaving(true);
    try {
      // Blank optional fields are sent as null, not "", so the API's nullable
      // numeric rules accept them.
      const body = {
        code: form.code,
        type: form.type,
        value: form.value,
        max_discount: form.max_discount || null,
        min_amount: form.min_amount || null,
        max_redemptions: form.max_redemptions || null,
        starts_at: form.starts_at || null,
        expires_at: form.expires_at || null,
        is_active: form.is_active,
      };
      const res = editingId
        ? await api.put(`/admin/coupons/${editingId}`, body)
        : await api.post('/admin/coupons', body);
      ensureApiSuccess(res, t.actionFailed);
      notify.success(editingId ? 'Coupon updated.' : 'Coupon created.');
      setForm(null);
      setEditingId(null);
      fetchRows();
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setSaving(false);
    }
  };

  const remove = async (row: CouponRow) => {
    const warning = row.redemptions_count > 0
      ? `"${row.code}" has been used ${row.redemptions_count} time(s), so it will be deactivated rather than deleted. Continue?`
      : `Delete coupon "${row.code}"?`;
    if (!window.confirm(warning)) return;

    try {
      const res = await api.delete(`/admin/coupons/${row.id}`);
      const payload = ensureApiSuccess(res, t.actionFailed);
      notify.success((payload as { message?: string })?.message || 'Coupon removed.');
      fetchRows();
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    }
  };

  const openDetails = async (id: number) => {
    try {
      const res = await api.get(`/admin/coupons/${id}`);
      setDetails(ensureApiSuccess<CouponDetails>(res, t.actionFailed) || null);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));
  const field = (key: keyof FormState, value: string | boolean) =>
    setForm((prev) => (prev ? { ...prev, [key]: value } : prev));

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.coupons}</h2>
        <div className="flex flex-wrap items-center gap-2">
          <select
            value={statusFilter}
            onChange={(e) => {
              setPage(1);
              setStatusFilter(e.target.value);
            }}
            className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm text-[#2f2b3d] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
          >
            <option value="">{t.allStatuses}</option>
            <option value="active">{t.active}</option>
            <option value="inactive">{t.inactive}</option>
            <option value="expired">{t.expired}</option>
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
          <Input className="h-9 w-[200px]" placeholder={t.searchCode} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button className="h-9" onClick={openCreate}>
            <Plus className="me-1 h-4 w-4" /> {t.newCoupon}
          </Button>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-4">
        {([
          [t.total, counts.total, 'text-[#7367f0]'],
          [t.active, counts.active, 'text-emerald-600 dark:text-emerald-300'],
          [t.expired, counts.expired, 'text-rose-600 dark:text-rose-300'],
          [t.redemptions, counts.redemptions, 'text-amber-600 dark:text-amber-300'],
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
                  <th className="px-4 py-3 text-start">{t.code}</th>
                  <th className="px-4 py-3 text-start">{t.discount}</th>
                  <th className="px-4 py-3 text-start">{t.minSpend}</th>
                  <th className="px-4 py-3 text-start">{t.used}</th>
                  <th className="px-4 py-3 text-start">{t.expires}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={7}>{t.noDataFound}</td></tr>
                ) : (
                  rows.map((row) => {
                    const status = statusOf(row);
                    return (
                      <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                        <td className="px-4 py-3 font-mono font-semibold">{row.code}</td>
                        <td className="px-4 py-3">
                          {row.value_label}
                          {row.max_discount != null ? (
                            <span className="text-xs text-[#8a8da8]"> (max £{row.max_discount.toFixed(2)})</span>
                          ) : null}
                        </td>
                        <td className="px-4 py-3">{row.min_amount != null ? `£${row.min_amount.toFixed(2)}` : '-'}</td>
                        <td className="px-4 py-3">
                          {row.redemptions_count}
                          <span className="text-[#8a8da8]"> / {row.max_redemptions ?? '∞'}</span>
                        </td>
                        <td className="px-4 py-3 whitespace-nowrap">{row.expires_at?.slice(0, 10) || '-'}</td>
                        <td className="px-4 py-3">
                          <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${status.tone}`}>{t[status.key]}</span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex gap-2">
                            <Button variant="secondary" className="h-9 px-3" onClick={() => openDetails(row.id)}>
                              <Eye className="h-4 w-4" />
                            </Button>
                            <Button variant="secondary" className="h-9 px-3" onClick={() => openEdit(row)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button variant="secondary" className="h-9 px-3" onClick={() => remove(row)}>
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
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

      {form ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setForm(null)}>
          <Card className="max-h-[85vh] w-full max-w-lg overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <CardContent className="space-y-4 p-5">
              <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">
                  {editingId ? t.editCoupon : t.newCoupon}
                </h3>
                <Button variant="secondary" className="h-9 px-3" onClick={() => setForm(null)}>
                  <X className="h-4 w-4" />
                </Button>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.code}</span>
                  <Input className="mt-1 font-mono" value={form.code} onChange={(e) => field('code', e.target.value)} placeholder="STUDENT50" />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.type}</span>
                  <select
                    value={form.type}
                    onChange={(e) => field('type', e.target.value)}
                    className="mt-1 h-10 w-full rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea]"
                  >
                    <option value="percentage">{t.percentage}</option>
                    <option value="fixed">{t.fixedAmount}</option>
                  </select>
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">Value {form.type === 'percentage' ? '(1-100)' : '(£)'}</span>
                  <Input className="mt-1" type="number" value={form.value} onChange={(e) => field('value', e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.maxDiscountOptional}</span>
                  <Input className="mt-1" type="number" value={form.max_discount} onChange={(e) => field('max_discount', e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.minSpendOptional}</span>
                  <Input className="mt-1" type="number" value={form.min_amount} onChange={(e) => field('min_amount', e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.totalUsesHint}</span>
                  <Input className="mt-1" type="number" value={form.max_redemptions} onChange={(e) => field('max_redemptions', e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.startsAtOptional}</span>
                  <Input className="mt-1" type="date" value={form.starts_at} onChange={(e) => field('starts_at', e.target.value)} />
                </label>
                <label className="text-sm">
                  <span className="text-xs text-[#8a8da8]">{t.expiresAtOptional}</span>
                  <Input className="mt-1" type="date" value={form.expires_at} onChange={(e) => field('expires_at', e.target.value)} />
                </label>
              </div>

              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.is_active} onChange={(e) => field('is_active', e.target.checked)} />
                {t.active}
              </label>

              <p className="rounded-lg bg-[#f8f7fb] p-3 text-xs text-[#8a8da8] dark:bg-[#383d56]">
                {t.couponOncePerUserNote}
              </p>

              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setForm(null)}>{t.cancel}</Button>
                <Button onClick={save} disabled={saving}>{saving ? '...' : t.save}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}

      {details ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setDetails(null)}>
          <Card className="max-h-[85vh] w-full max-w-2xl overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <CardContent className="space-y-4 p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-mono text-lg font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{details.code}</h3>
                  <p className="text-sm text-[#8a8da8]">
                    {details.value_label} off · used {details.redemptions_count} / {details.max_redemptions ?? '∞'}
                  </p>
                </div>
                <Button variant="secondary" className="h-9 px-3" onClick={() => setDetails(null)}>
                  <X className="h-4 w-4" />
                </Button>
              </div>

              <div>
                <p className="mb-2 text-sm font-semibold">{t.redemptions}</p>
                {details.redemptions.length ? (
                  <div className="overflow-x-auto rounded-xl border border-[#ececf3] dark:border-[#44485f]">
                    <table className="w-full text-sm">
                      <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                        <tr>
                          <th className="px-3 py-2 text-start">{t.user}</th>
                          <th className="px-3 py-2 text-start">{t.ad}</th>
                          <th className="px-3 py-2 text-start">{t.discount}</th>
                          <th className="px-3 py-2 text-start">{t.paid}</th>
                          <th className="px-3 py-2 text-start">{t.date}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {details.redemptions.map((r) => (
                          <tr key={r.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                            <td className="px-3 py-2">{r.user?.name || '-'}</td>
                            <td className="px-3 py-2">{r.ad?.title || '-'}</td>
                            <td className="px-3 py-2">£{r.discount_amount.toFixed(2)}</td>
                            <td className="px-3 py-2">£{r.final_amount.toFixed(2)}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{r.created_at || '-'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <div className="rounded-xl border border-dashed border-[#ececf3] p-3 text-sm text-[#8a8da8] dark:border-[#44485f]">
                    {t.couponNotUsedYet}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
