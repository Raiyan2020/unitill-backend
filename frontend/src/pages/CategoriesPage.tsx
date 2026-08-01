import { FolderTree, Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type LanguageRow = { id: number; code: string };
type Row = {
  id: number;
  parent_id: number | null;
  status: 'active' | 'inactive';
  sort: number;
  image: string | null;
  image_url: string | null;
  translations: Record<string, string>;
};
type PaginatedResponse<T> = { data: T[]; total: number };

export function CategoriesPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const navigate = useNavigate();
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
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [form, setForm] = useState({ status: 'active', sort: '0', translations: {} as Record<string, string> });
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/categories', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload = ensureApiSuccess<PaginatedResponse<Row>>(res, 'Failed to load categories');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load categories.');
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
    setImageFile(null);
    setForm({ status: 'active', sort: '0', translations: emptyTranslations() });
    setFormOpen(true);
  };

  const openEdit = (row: Row) => {
    const mapped = emptyTranslations();
    languages.forEach((l) => { mapped[l.code] = row.translations?.[l.code] || ''; });
    setEditing(row);
    setImageFile(null);
    setForm({ status: row.status, sort: String(row.sort || 0), translations: mapped });
    setFormOpen(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = new FormData();
      payload.append('status', form.status);
      payload.append('sort', String(Number(form.sort || 0)));
      Object.entries(form.translations).forEach(([code, value]) => payload.append(`translations[${code}]`, value));
      if (imageFile) payload.append('image', imageFile);

      if (editing) {
        const res = await api.post(`/admin/categories/${editing.id}?_method=PUT`, payload);
        ensureApiSuccess(res, 'Failed to update category');
      } else {
        const res = await api.post('/admin/categories', payload);
        ensureApiSuccess(res, 'Failed to create category');
      }
      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? 'Category updated successfully.' : 'Category created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update category.' : 'Failed to create category.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/categories/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete category');
      setDeleting(null);
      await fetchRows();
      notify.success('Category deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete category.');
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));
  const backendOrigin = (import.meta.env.VITE_BACKEND_ORIGIN as string | undefined) || window.location.origin;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.categories}</h2>
        <div className="flex items-center gap-2">
          <select value={pageSize} onChange={(e) => { setPage(1); setPageSize(Number(e.target.value)); }} className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
            {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
          <Input className="h-9 w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>+ Add Category</Button>
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
                  <th className="px-4 py-3 text-start">{t.name}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.sort}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={6} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={6}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">
                      {row.image ? (
                        <img
                          src={`${backendOrigin}/storage/${String(row.image).replace(/^\/+/, '')}`}
                          alt=""
                          className="h-10 w-10 rounded-full object-cover ring-2 ring-[#ececf3] dark:ring-[#44485f]"
                        />
                      ) : (
                        <div className="h-10 w-10 rounded-full bg-[#ececf3] dark:bg-[#44485f]" />
                      )}
                    </td>
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.translations?.en || row.translations?.ar || '-'}</td>
                    <td className="px-4 py-3">{row.status}</td>
                    <td className="px-4 py-3">{row.sort}</td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <Button size="icon" variant="secondary" title={t.subCategories} onClick={() => navigate(`/categories/${row.id}/subcategories`)}>
                          <FolderTree className="h-4 w-4" />
                        </Button>
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
                <CardTitle>{editing ? `Edit #${editing.id}` : 'Create Category'}</CardTitle>
                <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
              </CardHeader>
              <CardContent className="max-h-[78vh] space-y-4 overflow-y-auto p-4 md:max-h-[84vh] md:p-6">
                <div className="grid gap-3 md:grid-cols-3">
                  <Input type="file" accept="image/*" onChange={(e) => setImageFile(e.target.files?.[0] ?? null)} />
                  <Input placeholder={t.sort} value={form.sort} onChange={(e) => setForm((s) => ({ ...s, sort: e.target.value }))} />
                  <select value={form.status} onChange={(e) => setForm((s) => ({ ...s, status: e.target.value as 'active' | 'inactive' }))} className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
                    <option value="active">{t.active}</option>
                    <option value="inactive">{t.inactive}</option>
                  </select>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  {languages.map((language) => (
                    <div key={language.id}>
                      <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-[#8a8da8]">Name ({language.code})</p>
                      <Input value={form.translations[language.code] || ''} onChange={(e) => setForm((s) => ({ ...s, translations: { ...s.translations, [language.code]: e.target.value } }))} />
                    </div>
                  ))}
                </div>
                <div className="mt-2 flex justify-end gap-2 border-t border-[#ececf3] pt-3 dark:border-[#44485f]">
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
              <p className="mb-4 text-sm">Delete category #{deleting.id}?</p>
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
