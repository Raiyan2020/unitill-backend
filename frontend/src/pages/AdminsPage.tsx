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

const PRIMARY_ADMIN_EMAIL = 'admin@admin.net';

function isPrimaryAdminRow(row: AdminRow): boolean {
  return row.id === 1 || row.email.trim().toLowerCase() === PRIMARY_ADMIN_EMAIL;
}

/** Mirrors the API rule in AdminController: password => 'min:6'. */
const MIN_PASSWORD_LENGTH = 6;

/** Deliberately permissive: the API is the authority, this only catches typos. */
const EMAIL_PATTERN = /^[^s@]+@[^s@]+.[^s@]{2,}$/;

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
      const payload: PaginatedResponse<AdminRow> = ensureApiSuccess<PaginatedResponse<AdminRow>>(res, t.actionFailed);
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  const fetchRoles = async () => {
    const res = await api.get('/admin/roles');
    setRoles(ensureApiSuccess<RoleRow[]>(res, t.actionFailed) || []);
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
    if (isPrimaryAdminRow(row)) return;
    const res = await api.get(`/admin/admins/${row.id}`);
    const data = ensureApiSuccess<{ name?: string; email?: string; roles?: string[] }>(res, t.actionFailed);
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
    if (!form.name.trim()) {
      notify.error(t.nameRequired);
      return;
    }
    // Mirrors AdminController: email => 'required|email'. Checking the shape
    // here turns a raw 422 into a message the admin can act on.
    const email = form.email.trim();
    if (!email) {
      notify.error(t.emailRequired);
      return;
    }
    if (!EMAIL_PATTERN.test(email)) {
      notify.error(t.emailInvalid);
      return;
    }
    if (!editing && !form.password) {
      notify.error(t.passwordRequired);
      return;
    }
    // Only validate length when a password was actually typed: on edit an empty
    // field means "leave the current password alone".
    if (form.password && form.password.length < MIN_PASSWORD_LENGTH) {
      notify.error(t.passwordTooShort.replace('{min}', String(MIN_PASSWORD_LENGTH)));
      return;
    }
    if (form.password && !form.password_confirmation) {
      notify.error(t.passwordConfirmationRequired);
      return;
    }
    if (form.password !== form.password_confirmation) {
      notify.error(t.passwordConfirmationMismatch);
      return;
    }
    if (!form.role) {
      notify.error(t.roleRequired);
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
        ensureApiSuccess(res, t.actionFailed);
      } else {
        const res = await api.post('/admin/admins', {
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          roles: form.role ? [form.role] : [],
        });
        ensureApiSuccess(res, t.actionFailed);
      }
      setForm({ name: '', email: '', password: '', password_confirmation: '', role: '' });
      setEditing(null);
      setFormOpen(false);
      await fetchAdmins();
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
      const res = await api.delete(`/admin/admins/${deleting.id}`);
      ensureApiSuccess(res, t.actionFailed);
      setDeleting(null);
      await fetchAdmins();
      notify.success(t.deletedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
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
          <Input className="h-9 w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button size="sm" onClick={openCreate}>
            + {t.add} {t.admin}
          </Button>
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
                  <th className="px-4 py-3 text-start">{t.email}</th>
                  <th className="px-4 py-3 text-start">{t.roles}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={5} />
                ) : rows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-10 text-center text-sm text-[#8a8da8]" colSpan={5}>
                      {t.noDataFound}
                    </td>
                  </tr>
                ) : rows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.name}</td>
                    <td className="px-4 py-3">{row.email}</td>
                    <td className="px-4 py-3">{(row.roles || []).join(', ') || '-'}</td>
                    <td className="px-4 py-3">
                      {isPrimaryAdminRow(row) ? (
                        <span className="text-xs text-[#a5a7b8] dark:text-[#8a8da8]">—</span>
                      ) : (
                        <div className="flex gap-2">
                          <Button size="icon" variant="secondary" onClick={() => openEdit(row)} aria-label={t.edit}>
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button size="icon" variant="destructive" onClick={() => setDeleting(row)} aria-label={t.delete}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      )}
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
              <CardTitle>{editing ? `${t.edit} ${t.admin}` : `${t.add} ${t.admin}`}</CardTitle>
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
                <label className="space-y-1.5">
                  <span className="text-xs text-[#8a8da8]">{t.name}</span>
                  <Input placeholder={t.name} value={form.name} onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs text-[#8a8da8]">{t.email}</span>
                  <Input placeholder={t.email} value={form.email} onChange={(e) => setForm((s) => ({ ...s, email: e.target.value }))} />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs text-[#8a8da8]">{editing ? t.newPasswordOptional : t.password}</span>
                  <Input
                    type="password"
                    placeholder={editing ? t.newPasswordOptional : t.password}
                    value={form.password}
                    onChange={(e) => setForm((s) => ({ ...s, password: e.target.value }))}
                  />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs text-[#8a8da8]">{editing ? t.confirmNewPassword : t.confirmPassword}</span>
                  <Input
                    type="password"
                    placeholder={editing ? t.confirmNewPassword : t.confirmPassword}
                    value={form.password_confirmation}
                    onChange={(e) => setForm((s) => ({ ...s, password_confirmation: e.target.value }))}
                  />
                </label>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-medium text-[#6f6b7d] dark:text-[#b6b8cc]">{t.roles}</label>
                <select
                  value={form.role}
                  onChange={(e) => setForm((s) => ({ ...s, role: e.target.value }))}
                  className="h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
                >
                  <option value="">{t.selectRole}</option>
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
            <CardHeader><CardTitle>{t.confirmDeletion}</CardTitle></CardHeader>
            <CardContent>
              <p className="text-sm">{t.deleteAdminConfirmation}</p>
              <p className="mb-4 mt-1 text-sm font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">
                {deleting.name || deleting.email}
              </p>
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
