import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type PaymentRow = {
  id: number;
  name_ar: string;
  name_en: string;
  slug: string;
  status: 'active' | 'inactive';
  image: string | null;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function PaymentMethodsPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<PaymentRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<PaymentRow | null>(null);
  const [deleting, setDeleting] = useState<PaymentRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ name_ar: '', name_en: '', slug: '', status: 'active' });
  const [imageFile, setImageFile] = useState<File | null>(null);
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/payment-methods', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload: PaginatedResponse<PaymentRow> = ensureApiSuccess<PaginatedResponse<PaymentRow>>(res, 'Failed to load payment methods');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
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
  }, [search]);

  const openCreate = () => {
    setEditing(null);
    setFormOpen(true);
    setForm({ name_ar: '', name_en: '', slug: '', status: 'active' });
    setImageFile(null);
  };

  const openEdit = (row: PaymentRow) => {
    setEditing(row);
    setFormOpen(true);
    setForm({ name_ar: row.name_ar, name_en: row.name_en, slug: row.slug, status: row.status });
    setImageFile(null);
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = new FormData();
      payload.append('name_ar', form.name_ar);
      payload.append('name_en', form.name_en);
      payload.append('slug', form.slug);
      payload.append('status', form.status);
      if (imageFile) {
        payload.append('image', imageFile);
      }

      if (editing) {
        const res = await api.post(`/admin/payment-methods/${editing.id}?_method=PUT`, payload);
        ensureApiSuccess(res, 'Failed to update payment method');
      } else {
        const res = await api.post('/admin/payment-methods', payload);
        ensureApiSuccess(res, 'Failed to create payment method');
      }
      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? 'Payment method updated successfully.' : 'Payment method created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update payment method.' : 'Failed to create payment method.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/payment-methods/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete payment method');
      setDeleting(null);
      await fetchRows();
      notify.success('Payment method deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete payment method.');
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.paymentMethods}</h2>
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
          <Input className="h-9 w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>+ Add Payment Method</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.nameAr}</th>
                  <th className="px-4 py-3 text-start">{t.nameEn}</th>
                  <th className="px-4 py-3 text-start">{t.slug}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={6} />
                ) : rows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={6}>
                      {t.noDataFound}
                    </td>
                  </tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.name_ar}</td>
                    <td className="px-4 py-3">{row.name_en}</td>
                    <td className="px-4 py-3">{row.slug}</td>
                    <td className="px-4 py-3">{row.status}</td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <Button size="icon" variant="secondary" onClick={() => openEdit(row)}><Edit className="h-4 w-4" /></Button>
                        <Button size="icon" variant="destructive" onClick={() => setDeleting(row)}><Trash2 className="h-4 w-4" /></Button>
                      </div>
                    </td>
                  </tr>
                ))}
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

      {formOpen && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-xl">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{editing ? `Edit #${editing.id}` : 'Create Payment Method'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              <Input placeholder={t.nameAr} value={form.name_ar} onChange={(e) => setForm((s) => ({ ...s, name_ar: e.target.value }))} />
              <Input placeholder={t.nameEn} value={form.name_en} onChange={(e) => setForm((s) => ({ ...s, name_en: e.target.value }))} />
              <Input placeholder={t.slug} value={form.slug} onChange={(e) => setForm((s) => ({ ...s, slug: e.target.value }))} />
              <div className="flex items-center gap-3">
                <Input type="file" accept="image/*" onChange={(e) => setImageFile(e.target.files?.[0] ?? null)} />
                {editing?.image ? (
                  <a
                    className="text-xs text-[#7367f0] underline"
                    href={editing.image.startsWith('http') ? editing.image : `/storage/${editing.image}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    current
                  </a>
                ) : null}
              </div>
              <select
                value={form.status}
                onChange={(e) => setForm((s) => ({ ...s, status: e.target.value as 'active' | 'inactive' }))}
                className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
              >
                <option value="active">{t.active}</option>
                <option value="inactive">{t.inactive}</option>
              </select>
              <div className="col-span-full flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setFormOpen(false)}>{t.cancel}</Button>
                <Button onClick={save} disabled={saving}>{t.save}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {deleting && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-md">
            <CardHeader><CardTitle>Confirm deletion</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-4 text-sm">Delete payment method #{deleting.id}?</p>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setDeleting(null)}>{t.cancel}</Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={saving}>{t.delete}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
