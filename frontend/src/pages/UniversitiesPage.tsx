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

type UniversityRow = {
  id: number;
  name: string;
  country_code: string;
  state: string | null;
  city_id: number | null;
  city: string | null;
  status: 'active' | 'inactive';
  sort: number;
  domains: string[];
};

type CityOption = { id: number; name: string };

type PaginatedResponse<T> = { data: T[]; total: number };

const emptyForm = {
  name: '',
  country_code: 'GB',
  state: '',
  city_id: '',
  status: 'active',
  sort: '0',
  domains: [] as string[],
};

export function UniversitiesPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<UniversityRow[]>([]);
  const [cities, setCities] = useState<CityOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<UniversityRow | null>(null);
  const [deleting, setDeleting] = useState<UniversityRow | null>(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ ...emptyForm });
  const [domainInput, setDomainInput] = useState('');
  const didInitSearch = useRef(false);

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/universities', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload: PaginatedResponse<UniversityRow> = ensureApiSuccess<PaginatedResponse<UniversityRow>>(res, t.actionFailed);
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRows();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, pageSize]);

  useEffect(() => {
    (async () => {
      try {
        // Public endpoint (no admin permission gate) — this is reference
        // data for a picker, not something that needs cities.view on top of
        // universities.*, and a missing permission here previously left the
        // dropdown silently empty.
        const res = await api.get('/cities');
        const payload = ensureApiSuccess<CityOption[]>(res, t.actionFailed);
        setCities(payload || []);
      } catch (error) {
        notify.errorFrom(error, t.actionFailed);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
    setEditing(null);
    setForm({ ...emptyForm, domains: [] });
    setDomainInput('');
    setFormOpen(true);
  };

  const openEdit = (row: UniversityRow) => {
    setEditing(row);
    setForm({
      name: row.name,
      country_code: row.country_code || 'US',
      state: row.state || '',
      city_id: row.city_id ? String(row.city_id) : '',
      status: row.status,
      sort: String(row.sort ?? 0),
      domains: [...(row.domains || [])],
    });
    setDomainInput('');
    setFormOpen(true);
  };

  const addDomain = () => {
    const value = domainInput.trim().toLowerCase();
    if (!value) return;
    if (form.domains.includes(value)) {
      setDomainInput('');
      return;
    }
    setForm((s) => ({ ...s, domains: [...s.domains, value] }));
    setDomainInput('');
  };

  const removeDomain = (domain: string) => {
    setForm((s) => ({ ...s, domains: s.domains.filter((d) => d !== domain) }));
  };

  const save = async () => {
    if (!form.name.trim()) {
      notify.error(t.universityNameRequired);
      return;
    }
    if (!form.city_id) {
      notify.error(t.universityCityRequired);
      return;
    }
    setSaving(true);
    try {
      const payload = {
        name: form.name.trim(),
        country_code: (form.country_code || 'US').toUpperCase(),
        state: form.state.trim() || null,
        city_id: form.city_id ? Number(form.city_id) : null,
        status: form.status,
        sort: toInteger(form.sort),
        domains: form.domains,
      };

      if (editing) {
        const res = await api.put(`/admin/universities/${editing.id}`, payload);
        ensureApiSuccess(res, t.actionFailed);
      } else {
        const res = await api.post('/admin/universities', payload);
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
      const res = await api.delete(`/admin/universities/${deleting.id}`);
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
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.universities}</h2>
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
          <Button size="sm" onClick={openCreate}>+ {t.add} {t.university}</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.name}</th>
                  <th className="px-4 py-3 text-start">{t.city}</th>
                  <th className="px-4 py-3 text-start">{t.domains}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
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
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3 font-medium">{row.name}</td>
                    <td className="px-4 py-3">{row.city || '-'}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        {(row.domains || []).length === 0 ? (
                          <span className="text-[#8a8da8]">-</span>
                        ) : (
                          row.domains.map((domain) => (
                            <span key={domain} className="rounded-md bg-[#7367f0]/10 px-2 py-0.5 text-xs text-[#7367f0]">
                              {domain}
                            </span>
                          ))
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={row.status === 'active' ? 'text-[#28c76f]' : 'text-[#ea5455]'}>{row.status}</span>
                    </td>
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
              <CardTitle>{editing ? `${t.edit} ${t.university}` : `${t.add} ${t.university}`}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setFormOpen(false)}><X className="h-4 w-4" /></Button>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              <Input placeholder={t.name} className="md:col-span-2" value={form.name} onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
              <Input placeholder={t.stateHint} value={form.state} onChange={(e) => setForm((s) => ({ ...s, state: e.target.value }))} />
              <select
                value={form.city_id}
                onChange={(e) => setForm((s) => ({ ...s, city_id: e.target.value }))}
                className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
              >
                <option value="">{t.selectCity} *</option>
                {cities.map((city) => (
                  <option key={city.id} value={city.id}>{city.name}</option>
                ))}
              </select>
              <Input placeholder={t.countryCodeHint} value={form.country_code} onChange={(e) => setForm((s) => ({ ...s, country_code: e.target.value }))} />
              <Input placeholder={t.sort} inputMode="numeric" value={form.sort} onChange={(e) => setForm((s) => ({ ...s, sort: digitsOnly(e.target.value) }))} />

              <select value={form.status} onChange={(e) => setForm((s) => ({ ...s, status: e.target.value }))} className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]">
                <option value="active">{t.active}</option>
                <option value="inactive">{t.inactive}</option>
              </select>

              <div className="md:col-span-2">
                <label className="mb-1 block text-sm font-medium text-[#6f6b7d] dark:text-[#b6b8cc]">{t.emailDomains}</label>
                <div className="flex gap-2">
                  <Input
                    placeholder={t.domainHint}
                    value={domainInput}
                    onChange={(e) => setDomainInput(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        addDomain();
                      }
                    }}
                  />
                  <Button type="button" variant="secondary" onClick={addDomain}>{t.add}</Button>
                </div>
                <div className="mt-2 flex flex-wrap gap-2">
                  {form.domains.length === 0 ? (
                    <span className="text-xs text-[#8a8da8]">{t.noDomainsAdded}</span>
                  ) : (
                    form.domains.map((domain) => (
                      <span key={domain} className="inline-flex items-center gap-1 rounded-md bg-[#7367f0]/10 px-2 py-1 text-xs text-[#7367f0]">
                        {domain}
                        <button type="button" onClick={() => removeDomain(domain)} className="hover:text-[#ea5455]">
                          <X className="h-3 w-3" />
                        </button>
                      </span>
                    ))
                  )}
                </div>
              </div>

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
              <p className="mb-4 text-sm">{t.deleteUniversityConfirmation}</p>
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
