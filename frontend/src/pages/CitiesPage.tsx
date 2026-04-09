import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type CountryOption = { id: number; country_code: string; translations: Record<string, string> };
type LanguageRow = { id: number; code: string; name: string; native_name: string };

type CityRow = {
  id: number;
  translations: Record<string, string>;
  country_id: number | null;
  country_name: string;
  status: 'active' | 'inactive';
  code: string | null;
  sort: number;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function CitiesPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [searchParams] = useSearchParams();
  const selectedCountryId = searchParams.get('country_id') || '';

  const [rows, setRows] = useState<CityRow[]>([]);
  const [countries, setCountries] = useState<CountryOption[]>([]);
  const [languages, setLanguages] = useState<LanguageRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<CityRow | null>(null);
  const [deleting, setDeleting] = useState<CityRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ country_id: '', status: 'active', code: '', sort: '0', translations: {} as Record<string, string> });
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/cities', {
        params: {
          page,
          per_page: pageSize,
          search: search || undefined,
          country_id: selectedCountryId || undefined,
        },
      });
      const payload: PaginatedResponse<CityRow> = ensureApiSuccess<PaginatedResponse<CityRow>>(res, 'Failed to load cities');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  const fetchCountries = async () => {
    const res = await api.get('/admin/countries', { params: { per_page: 200 } });
    const payload = ensureApiSuccess<PaginatedResponse<CountryOption>>(res, 'Failed to load countries');
    setCountries(payload?.data || []);
  };

  const fetchLanguages = async () => {
    const res = await api.get('/admin/languages', { params: { per_page: 100 } });
    const payload = ensureApiSuccess<PaginatedResponse<LanguageRow>>(res, 'Failed to load languages');
    setLanguages((payload?.data || []).filter((l: LanguageRow) => l.code));
  };

  useEffect(() => {
    fetchRows();
    fetchCountries();
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
  }, [search, selectedCountryId]);

  const openCreate = () => {
    const translations: Record<string, string> = {};
    languages.forEach((l) => {
      translations[l.code] = '';
    });

    setEditing(null);
    setFormOpen(true);
    setForm({ country_id: selectedCountryId, status: 'active', code: '', sort: '0', translations });
  };

  const openEdit = (row: CityRow) => {
    const translations: Record<string, string> = {};
    languages.forEach((l) => {
      translations[l.code] = row.translations?.[l.code] || '';
    });

    setEditing(row);
    setFormOpen(true);
    setForm({
      country_id: row.country_id ? String(row.country_id) : '',
      status: row.status,
      code: row.code || '',
      sort: String(row.sort ?? 0),
      translations,
    });
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = {
        country_id: form.country_id ? Number(form.country_id) : null,
        status: form.status,
        code: form.code || null,
        sort: Number(form.sort || 0),
        translations: form.translations,
      };

      if (editing) {
        const res = await api.put(`/admin/cities/${editing.id}`, payload);
        ensureApiSuccess(res, 'Failed to update city');
      } else {
        const res = await api.post('/admin/cities', payload);
        ensureApiSuccess(res, 'Failed to create city');
      }

      setFormOpen(false);
      await fetchRows();
      notify.success(editing ? 'City updated successfully.' : 'City created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update city.' : 'Failed to create city.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/cities/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete city');
      setDeleting(null);
      await fetchRows();
      notify.success('City deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete city.');
    } finally {
      setSaving(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.cities}</h2>
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
          <Input className="h-9 w-[220px]" placeholder="Search" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>+ Add City</Button>
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
                  <th className="px-4 py-3 text-start">Country</th>
                  <th className="px-4 py-3 text-start">Status</th>
                  <th className="px-4 py-3 text-start">Code</th>
                  <th className="px-4 py-3 text-start">Sort</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={7}>{t.noDataFound}</td></tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.translations?.en || row.translations?.ar || Object.values(row.translations || {})[0] || '-'}</td>
                    <td className="px-4 py-3">{row.country_name || '-'}</td>
                    <td className="px-4 py-3">{row.status}</td>
                    <td className="px-4 py-3">{row.code || '-'}</td>
                    <td className="px-4 py-3">{row.sort}</td>
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
          <Card className="w-full max-w-2xl">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{editing ? `Edit #${editing.id}` : 'Create City'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              <select value={form.country_id} onChange={(e) => setForm((s) => ({ ...s, country_id: e.target.value }))} className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
                <option value="">Select country</option>
                {countries.map((country) => (
                  <option key={country.id} value={country.id}>{country.translations?.en || country.country_code}</option>
                ))}
              </select>

              <Input placeholder="Code" value={form.code} onChange={(e) => setForm((s) => ({ ...s, code: e.target.value }))} />

              {languages.map((language) => (
                <Input
                  key={language.id}
                  placeholder={`Name (${language.code})`}
                  value={form.translations[language.code] || ''}
                  onChange={(e) =>
                    setForm((s) => ({
                      ...s,
                      translations: { ...s.translations, [language.code]: e.target.value },
                    }))
                  }
                />
              ))}

              <select value={form.status} onChange={(e) => setForm((s) => ({ ...s, status: e.target.value }))} className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
              <Input placeholder="Sort" value={form.sort} onChange={(e) => setForm((s) => ({ ...s, sort: e.target.value }))} />

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
              <p className="mb-4 text-sm">Delete city #{deleting.id}?</p>
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
