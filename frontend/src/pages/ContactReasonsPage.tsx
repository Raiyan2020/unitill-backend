import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type LanguageRow = { id: number; code: string };
type Row = { id: number; is_active: boolean; sort_order: number; translations: Record<string, string> };
type PaginatedResponse<T> = { data: T[]; total: number };

export function ContactReasonsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<Row[]>([]);
  const [languages, setLanguages] = useState<LanguageRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Row | null>(null);
  const [deleting, setDeleting] = useState<Row | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ is_active: true, sort_order: '0', translations: {} as Record<string, string> });
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/contact-reasons', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload = ensureApiSuccess<PaginatedResponse<Row>>(res, 'Failed to load contact reasons');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load contact reasons.');
    } finally {
      setLoading(false);
    }
  };

  const fetchLanguages = async () => {
    const res = await api.get('/admin/languages', { params: { per_page: 100 } });
    const payload = ensureApiSuccess<PaginatedResponse<LanguageRow>>(res, 'Failed to load languages');
    setLanguages(payload?.data || []);
  };

  useEffect(() => {
    fetchRows();
    fetchLanguages();
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

  const emptyTranslations = () => {
    const out: Record<string, string> = {};
    languages.forEach((l) => { out[l.code] = ''; });
    return out;
  };

  const openCreate = () => {
    setEditing(null);
    setForm({ is_active: true, sort_order: '0', translations: emptyTranslations() });
    setFormOpen(true);
  };

  const openEdit = (row: Row) => {
    const tMap = emptyTranslations();
    languages.forEach((l) => { tMap[l.code] = row.translations?.[l.code] || ''; });
    setEditing(row);
    setForm({ is_active: row.is_active, sort_order: String(row.sort_order || 0), translations: tMap });
    setFormOpen(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = { is_active: form.is_active, sort_order: Number(form.sort_order || 0), translations: form.translations };
      if (editing) {
        const res = await api.put(`/admin/contact-reasons/${editing.id}`, payload);
        ensureApiSuccess(res, 'Failed to update contact reason');
      } else {
        const res = await api.post('/admin/contact-reasons', payload);
        ensureApiSuccess(res, 'Failed to create contact reason');
      }
      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? 'Contact reason updated successfully.' : 'Contact reason created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update contact reason.' : 'Failed to create contact reason.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/contact-reasons/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete contact reason');
      setDeleting(null);
      await fetchRows();
      notify.success('Contact reason deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete contact reason.');
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.contactReasons}</h2>
        <div className="flex items-center gap-2">
          <select value={pageSize} onChange={(e) => { setPage(1); setPageSize(Number(e.target.value)); }} className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
            {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
          <Input className="h-9 w-[220px]" placeholder="Search" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>+ Add Contact Reason</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">ID</th>
                  <th className="px-4 py-3 text-start">Name</th>
                  <th className="px-4 py-3 text-start">Active</th>
                  <th className="px-4 py-3 text-start">Sort</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={5} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={5}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.translations?.en || row.translations?.ar || '-'}</td>
                    <td className="px-4 py-3">{row.is_active ? 'Yes' : 'No'}</td>
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
          <TableFooter page={page} pageSize={pageSize} total={total} onPrev={() => setPage((p) => p - 1)} onNext={() => setPage((p) => p + 1)} prevDisabled={page <= 1} nextDisabled={page >= pagesCount} />
        </CardContent>
      </Card>

      {formOpen && (
        <div className="fixed inset-0 z-40 overflow-y-auto bg-black/35 p-3 md:p-4">
          <div className="flex min-h-full items-start justify-center py-4 md:items-center md:py-8">
            <Card className="w-full max-w-2xl overflow-hidden rounded-2xl">
            <CardHeader className="sticky top-0 z-10 flex flex-row items-center justify-between space-y-0 border-b border-[#ececf3] bg-white/95 backdrop-blur dark:border-[#44485f] dark:bg-[#2f3349]/95">
              <CardTitle>{editing ? `Edit #${editing.id}` : 'Create Contact Reason'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="max-h-[72vh] space-y-5 overflow-y-auto p-4 md:max-h-[78vh] md:p-6">
              <div className="grid gap-4 md:grid-cols-2">
                <Input placeholder="Sort" value={form.sort_order} onChange={(e) => setForm((s) => ({ ...s, sort_order: e.target.value }))} />
                <label className="flex items-center gap-2 rounded-xl border border-[#dbdbe8] px-3 text-sm dark:border-[#4a4f68]"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm((s) => ({ ...s, is_active: e.target.checked }))} /> Active</label>
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                {languages.map((language) => (
                  <div key={language.id}>
                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-[#8a8da8]">
                      Name ({language.code})
                    </p>
                    <Input
                      className="h-12"
                      placeholder={`Enter name in ${language.code}`}
                      value={form.translations[language.code] || ''}
                      onChange={(e) => setForm((s) => ({ ...s, translations: { ...s.translations, [language.code]: e.target.value } }))}
                    />
                  </div>
                ))}
              </div>
              <div className="sticky bottom-0 -mx-4 -mb-4 flex justify-end gap-2 border-t border-[#ececf3] bg-white/95 px-4 py-3 backdrop-blur dark:border-[#44485f] dark:bg-[#2f3349]/95 md:-mx-6 md:-mb-6 md:px-6">
                <Button variant="secondary" onClick={() => setFormOpen(false)}>{t.cancel}</Button>
                <Button onClick={save} disabled={saving}>{t.save}</Button>
              </div>
            </CardContent>
          </Card>
          </div>
        </div>
      )}

      {deleting && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-md">
            <CardHeader><CardTitle>Confirm deletion</CardTitle></CardHeader>
            <CardContent>
              <p className="mb-4 text-sm">Delete contact reason #{deleting.id}?</p>
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
