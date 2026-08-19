import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { digitsOnly, toInteger } from '../lib/form';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type LanguageRow = {
  id: number;
  code: string;
  name: string;
  native_name: string;
  direction: 'ltr' | 'rtl';
  is_active: boolean;
  is_default: boolean;
  sort_order: number;
};

type PaginatedResponse<T> = { data: T[] };

/** Mirrors LanguageController: code => 'max:10'. */
const LANGUAGE_CODE_MAX_LENGTH = 10;

export function LanguagesPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<LanguageRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<LanguageRow | null>(null);
  const [deleting, setDeleting] = useState<LanguageRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    code: '',
    name: '',
    native_name: '',
    direction: 'ltr',
    is_active: true,
    is_default: false,
    sort_order: '0',
  });
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/languages', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload: PaginatedResponse<LanguageRow> & { total?: number } = ensureApiSuccess<PaginatedResponse<LanguageRow>>(res, t.actionFailed);
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
    setForm({ code: '', name: '', native_name: '', direction: 'ltr', is_active: true, is_default: false, sort_order: '0' });
  };

  const openEdit = (row: LanguageRow) => {
    setEditing(row);
    setFormOpen(true);
    setForm({
      code: row.code,
      name: row.name,
      native_name: row.native_name,
      direction: row.direction,
      is_active: row.is_active,
      is_default: row.is_default,
      sort_order: String(row.sort_order ?? 0),
    });
  };

  const save = async () => {
    const code = form.code.trim();
    if (!code) {
      notify.error(t.languageCodeRequired);
      return;
    }
    if (code.length > LANGUAGE_CODE_MAX_LENGTH) {
      notify.error(t.languageCodeLength.replace('{max}', String(LANGUAGE_CODE_MAX_LENGTH)));
      return;
    }
    if (!form.name.trim() || !form.native_name.trim()) {
      notify.error(t.nameRequiredAnyLanguage);
      return;
    }

    setSaving(true);
    try {
      const payload = { ...form, sort_order: toInteger(form.sort_order) };
      if (editing) {
        const res = await api.put(`/admin/languages/${editing.id}`, payload);
        ensureApiSuccess(res, t.actionFailed);
      } else {
        const res = await api.post('/admin/languages', payload);
        ensureApiSuccess(res, t.actionFailed);
      }
      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? t.updatedSuccessfully : t.createdSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/languages/${deleting.id}`);
      ensureApiSuccess(res, t.actionFailed);
      setDeleting(null);
      await fetchRows();
      notify.success(t.deletedSuccessfully);
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
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.languages}</h2>
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
          <Button size="sm" onClick={openCreate}>+ {t.add} {t.language}</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.code}</th>
                  <th className="px-4 py-3 text-start">{t.name}</th>
                  <th className="px-4 py-3 text-start">{t.native}</th>
                  <th className="px-4 py-3 text-start">{t.direction}</th>
                  <th className="px-4 py-3 text-start">{t.active}</th>
                  <th className="px-4 py-3 text-start">{t.isDefault}</th>
                  <th className="px-4 py-3 text-start">{t.sort}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={9} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={9}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.code}</td>
                    <td className="px-4 py-3">{row.name}</td>
                    <td className="px-4 py-3">{row.native_name}</td>
                    <td className="px-4 py-3">{row.direction}</td>
                    <td className="px-4 py-3">{row.is_active ? 'Yes' : 'No'}</td>
                    <td className="px-4 py-3">{row.is_default ? 'Yes' : 'No'}</td>
                    <td className="px-4 py-3">{row.sort_order}</td>
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
              <CardTitle>{editing ? `${t.edit}: ${editing.name || editing.code}` : `${t.add} ${t.language}`}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.code}</span>
                <Input placeholder={t.code} value={form.code} onChange={(e) => setForm((s) => ({ ...s, code: e.target.value }))} />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.sort}</span>
                <Input placeholder={t.sort} inputMode="numeric" value={form.sort_order} onChange={(e) => setForm((s) => ({ ...s, sort_order: digitsOnly(e.target.value) }))} />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.name}</span>
                <Input placeholder={t.name} value={form.name} onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.nativeName}</span>
                <Input placeholder={t.nativeName} value={form.native_name} onChange={(e) => setForm((s) => ({ ...s, native_name: e.target.value }))} />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.direction}</span>
                <select value={form.direction} onChange={(e) => setForm((s) => ({ ...s, direction: e.target.value as 'ltr' | 'rtl' }))} className="h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
                  <option value="ltr">ltr</option>
                  <option value="rtl">rtl</option>
                </select>
              </label>
              <label className="space-y-1.5">
                <span className="text-xs text-[#8a8da8]">{t.isActiveQuestion}</span>
                <select
                  value={form.is_active ? 'active' : 'inactive'}
                  onChange={(e) => setForm((s) => ({ ...s, is_active: e.target.value === 'active' }))}
                  className="h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
                >
                  <option value="active">{t.active}</option>
                  <option value="inactive">{t.inactive}</option>
                </select>
              </label>
              <label className="col-span-full flex items-center gap-2 rounded-xl border border-[#ececf3] px-3 py-2.5 text-sm dark:border-[#44485f]">
                <input type="checkbox" checked={form.is_default} onChange={(e) => setForm((s) => ({ ...s, is_default: e.target.checked }))} /> {t.isDefault}
              </label>
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
            <CardHeader><CardTitle>{t.confirmDeletion}</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-4 text-sm">{t.deleteConfirmation}</p>
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
