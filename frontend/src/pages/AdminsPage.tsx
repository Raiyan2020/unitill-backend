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

type AdminRow = {
  id: number;
  name: string;
  email: string;
  roles: string[];
};

type RoleRow = {
  id: number;
  name: string;
};

type PaginatedResponse<T> = {
  data: T[];
  total: number;
  current_page: number;
};

export function AdminsPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<AdminRow[]>([]);
  const [roles, setRoles] = useState<RoleRow[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [pageSize, setPageSize] = useState(10);

  const [editing, setEditing] = useState<AdminRow | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [deleting, setDeleting] = useState<AdminRow | null>(null);
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
  });
  const [saving, setSaving] = useState(false);
  const didInitSearch = useRef(false);

  const fetchAdmins = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/admins', { params: { page, per_page: pageSize, search: search || undefined } });
      const payload: PaginatedResponse<AdminRow> = ensureApiSuccess<PaginatedResponse<AdminRow>>(res, 'Failed to load admins');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  const fetchRoles = async () => {
    const res = await api.get('/admin/roles');
    setRoles(ensureApiSuccess<RoleRow[]>(res, 'Failed to load roles') || []);
  };

  useEffect(() => {
    fetchAdmins();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page]);

  useEffect(() => {
    fetchRoles();
  }, []);

  useEffect(() => {
    if (!didInitSearch.current) {
      didInitSearch.current = true;
      return;
    }
    const timer = setTimeout(() => {
      setPage(1);
      fetchAdmins();
    }, 350);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const openCreate = () => {
    setFormOpen(true);
    setEditing(null);
    setForm({ name: '', email: '', password: '', password_confirmation: '', role: '' });
  };

  const openEdit = async (row: AdminRow) => {
    const res = await api.get(`/admin/admins/${row.id}`);
    const data = ensureApiSuccess<{ name?: string; email?: string; roles?: string[] }>(res, 'Failed to load admin details');
    setFormOpen(true);
    setEditing(row);
    setForm({
      name: data?.name || row.name,
      email: data?.email || row.email,
      password: '',
      password_confirmation: '',
      role: data?.roles?.[0] || row.roles?.[0] || '',
    });
  };

  const save = async () => {
    if (!editing && !form.password) {
      notify.error('Password is required.');
      return;
    }
    if (form.password !== form.password_confirmation) {
      notify.error('Password confirmation does not match.');
      return;
    }

    setSaving(true);
    try {
      if (editing) {
        const res = await api.put(`/admin/admins/${editing.id}`, {
          name: form.name,
          email: form.email,
          password: form.password || undefined,
          roles: form.role ? [form.role] : [],
        });
        ensureApiSuccess(res, 'Failed to update admin');
      } else {
        const res = await api.post('/admin/admins', {
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          roles: form.role ? [form.role] : [],
        });
        ensureApiSuccess(res, 'Failed to create admin');
      }
      setForm({ name: '', email: '', password: '', password_confirmation: '', role: '' });
      setEditing(null);
      setFormOpen(false);
      await fetchAdmins();
      notify.success(editing ? 'Admin updated successfully.' : 'Admin created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update admin.' : 'Failed to create admin.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/admins/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete admin');
      setDeleting(null);
      await fetchAdmins();
      notify.success('Admin deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete admin.');
    } finally {
      setSaving(false);
    }
  };

  const pages = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.admins}</h2>
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
          <Button size="sm" onClick={openCreate}>
            + Add Admin
          </Button>
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
                  <th className="px-4 py-3 text-start">{t.email}</th>
                  <th className="px-4 py-3 text-start">{t.roles}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={5} />
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.name}</td>
                    <td className="px-4 py-3">{row.email}</td>
                    <td className="px-4 py-3">{(row.roles || []).join(', ') || '-'}</td>
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
            nextDisabled={page >= pages}
          />
        </CardContent>
      </Card>

      {formOpen && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4">
          <Card className="w-full max-w-xl">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{editing ? `Edit #${editing.id}` : 'Create Admin'}</CardTitle>
              <Button
                variant="ghost"
                size="icon"
                onClick={() => {
                  setEditing(null);
                  setFormOpen(false);
                  setForm({ name: '', email: '', password: '', password_confirmation: '', role: '' });
                }}
              >
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent className="space-y-5">
              <div className="grid gap-3.5 md:grid-cols-2">
                <Input placeholder="Name" value={form.name} onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
                <Input placeholder={t.email} value={form.email} onChange={(e) => setForm((s) => ({ ...s, email: e.target.value }))} />
                <Input
                  type="password"
                  placeholder={editing ? 'New password (optional)' : 'Password'}
                  value={form.password}
                  onChange={(e) => setForm((s) => ({ ...s, password: e.target.value }))}
                />
                <Input
                  type="password"
                  placeholder={editing ? 'Confirm new password' : 'Confirm password'}
                  value={form.password_confirmation}
                  onChange={(e) => setForm((s) => ({ ...s, password_confirmation: e.target.value }))}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[#6f6b7d] dark:text-[#b6b8cc]">{t.roles}</label>
                <select
                  value={form.role}
                  onChange={(e) => setForm((s) => ({ ...s, role: e.target.value }))}
                  className="h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
                >
                  <option value="">Select role</option>
                  {roles.map((role) => (
                    <option key={role.id} value={role.name}>
                      {role.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex justify-end gap-2 pt-1">
                <Button
                  variant="secondary"
                  onClick={() => {
                    setEditing(null);
                    setFormOpen(false);
                    setForm({ name: '', email: '', password: '', password_confirmation: '', role: '' });
                  }}
                >
                  {t.cancel}
                </Button>
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
              <p className="mb-4 text-sm">Delete admin #{deleting.id}?</p>
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
