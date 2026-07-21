import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type LanguageRow = { id: number; code: string };
type Row = { id: number; parent_id: number | null; translations: Record<string, string> };
type PaginatedResponse<T> = { data: T[]; total: number };

export function SubCategoriesPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const navigate = useNavigate();
  const { categoryId } = useParams();
  const parentId = Number(categoryId || 0);
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
  const [form, setForm] = useState({ translations: {} as Record<string, string> });
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    if (!parentId) return;
    setLoading(true);
    try {
      const res = await api.get('/admin/categories', { params: { parent_id: parentId, page, per_page: pageSize, search: search || undefined } });
      const payload = ensureApiSuccess<PaginatedResponse<Row>>(res, 'Failed to load sub categories');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load sub categories.');
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
  }, [parentId, page, pageSize]);

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
  }, [search, parentId]);

  const openCreate = () => {
    setEditing(null);
    const translations: Record<string, string> = {};
    languages.forEach((l) => {
      translations[l.code] = '';
    });
    setForm({ translations });
    setFormOpen(true);
  };

  const openEdit = (row: Row) => {
    setEditing(row);
    const translations: Record<string, string> = {};
    languages.forEach((l) => {
      translations[l.code] = row.translations?.[l.code] || '';
    });
    setForm({ translations });
    setFormOpen(true);
  };

  const save = async () => {
    const hasAnyTitle = Object.values(form.translations).some((v) => String(v || '').trim() !== '');
    if (!hasAnyTitle) {
      notify.error('At least one translation is required.');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        parent_id: parentId,
        status: 'active',
        sort: 0,
        translations: form.translations,
      };
      if (editing) {
        const res = await api.put(`/admin/categories/${editing.id}`, payload);
        ensureApiSuccess(res, 'Failed to update sub category');
      } else {
        const res = await api.post('/admin/categories', payload);
        ensureApiSuccess(res, 'Failed to create sub category');
      }
      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? 'Sub category updated successfully.' : 'Sub category created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update sub category.' : 'Failed to create sub category.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/categories/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete sub category');
      setDeleting(null);
      await fetchRows();
      notify.success('Sub category deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete sub category.');
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.subCategories}</h2>
          <p className="text-xs text-[#8a8da8]">Parent category #{parentId}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" size="sm" onClick={() => navigate('/categories')}>Back</Button>
          <select value={pageSize} onChange={(e) => { setPage(1); setPageSize(Number(e.target.value)); }} className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
            {[10, 25, 50, 100].map((size) => <option key={size} value={size}>{size}</option>)}
          </select>
          <Input className="h-9 w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>+ Add Sub Category</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.title}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={3} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={3}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.translations?.en || row.translations?.ar || '-'}</td>
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
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-xl">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{editing ? `Edit #${editing.id}` : 'Create Sub Category'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                {languages.map((language) => (
                  <div key={language.id}>
                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-[#8a8da8]">Title ({language.code})</p>
                    <Input
                      value={form.translations[language.code] || ''}
                      onChange={(e) =>
                        setForm((s) => ({
                          ...s,
                          translations: { ...s.translations, [language.code]: e.target.value },
                        }))
                      }
                    />
                  </div>
                ))}
              </div>
              <div className="flex justify-end gap-2">
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
              <p className="mb-4 text-sm">Delete sub category #{deleting.id}?</p>
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
