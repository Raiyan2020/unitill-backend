import { BadgeCheck, Edit, Eye, Heart, Plus, Smartphone, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type UserStatus = '1' | '2' | '3';

type UserRow = {
  id: number;
  first_name: string | null;
  last_name: string | null;
  name: string | null;
  email: string | null;
  phone: string | null;
  country_code: string | null;
  status: UserStatus;
  is_trusted_seller?: boolean;
};

type PaginatedResponse<T> = {
  data: T[];
  current_page: number;
  per_page: number;
  total: number;
};

const statusSelectClass = (status: UserStatus) => {
  if (status === '1') {
    return 'border-emerald-300/70 bg-emerald-500/15 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/20 dark:text-emerald-300';
  }

  if (status === '2') {
    return 'border-amber-300/70 bg-amber-500/15 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/20 dark:text-amber-300';
  }

  return 'border-rose-300/70 bg-rose-500/15 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/20 dark:text-rose-300';
};

export function UsersPage() {
  const navigate = useNavigate();
  const { t } = useI18n();
  const notify = useNotify();
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<UserRow[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);

  const [editingUser, setEditingUser] = useState<UserRow | null>(null);
  const [deletingUser, setDeletingUser] = useState<UserRow | null>(null);
  const [creatingUser, setCreatingUser] = useState(false);
  const [formData, setFormData] = useState<Record<string, string>>({});
  const [createData, setCreateData] = useState<Record<string, string>>({ status: '1' });
  const [saving, setSaving] = useState(false);
  const didInitSearch = useRef(false);

  const fetchUsers = async (searchValue = search) => {
    setLoading(true);
    try {
      const res = await api.get('/admin/users', {
        params: { page, per_page: pageSize, search: searchValue || undefined },
      });

      const payload: PaginatedResponse<UserRow> = res?.data?.data;
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, pageSize]);

  useEffect(() => {
    if (!didInitSearch.current) {
      didInitSearch.current = true;
      return;
    }
    const timer = setTimeout(() => {
      setPage(1);
      fetchUsers(search);
    }, 350);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const openEdit = (user: UserRow) => {
    setEditingUser(user);
    setFormData({
      first_name: user.first_name || '',
      last_name: user.last_name || '',
      email: user.email || '',
      phone: user.phone || '',
      country_code: user.country_code || '',
      status: user.status || '1',
    });
  };

  const openCreate = () => {
    setCreateData({ status: '1' });
    setCreatingUser(true);
  };

  const submitCreate = async () => {
    setSaving(true);
    try {
      await api.post('/admin/users', createData);
      setCreatingUser(false);
      setPage(1);
      await fetchUsers();
      notify.success(t.userCreatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSaving(false);
    }
  };

  const submitEdit = async () => {
    if (!editingUser) return;

    setSaving(true);
    try {
      await api.put(`/admin/users/${editingUser.id}`, formData);
      setEditingUser(null);
      await fetchUsers();
      notify.success(t.userUpdatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deletingUser) return;

    setSaving(true);
    try {
      await api.delete(`/admin/users/${deletingUser.id}`);
      setDeletingUser(null);
      await fetchUsers();
      notify.success(t.userDeletedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSaving(false);
    }
  };

  const updateStatus = async (user: UserRow, status: UserStatus) => {
    try {
      await api.put(`/admin/users/${user.id}`, { status });
      await fetchUsers();
      notify.success(t.userUpdatedSuccessfully);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
      await fetchUsers();
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  const statusLabel = useMemo(
    () => ({
      '1': t.active,
      '2': t.pending,
      '3': t.disabled,
    }),
    [t.active, t.pending, t.disabled],
  );

  return (
    <div className="space-y-4 animate-in fade-in duration-300">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.users}</h2>
          <p className="text-sm text-[#8a8da8] dark:text-[#a2a5be]">{t.manageUsersSubtitle}</p>
        </div>

        <div className="ms-auto flex w-full max-w-[520px] items-center justify-end gap-2">
          <Button size="sm" onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t.createUser}
          </Button>
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
          <Input className="h-9 max-w-[220px]" placeholder={t.search} value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <Card className="shadow-[0_10px_24px_rgba(47,43,61,0.08)] dark:shadow-[0_10px_24px_rgba(0,0,0,0.25)]">
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">{t.id}</th>
                  <th className="px-4 py-3 text-start">{t.firstName}</th>
                  <th className="px-4 py-3 text-start">{t.lastName}</th>
                  <th className="px-4 py-3 text-start">{t.email}</th>
                  <th className="px-4 py-3 text-start">{t.phone}</th>
                  <th className="px-4 py-3 text-start">{t.status}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-10 text-center text-sm text-[#8a8da8]" colSpan={7}>
                      {t.noDataFound}
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3">
                        <span className="inline-flex items-center gap-1.5">
                          {row.first_name || '-'}
                          {row.is_trusted_seller ? (
                            <button
                              type="button"
                              className="inline-flex items-center"
                              title={t.viewVerificationDetails}
                              onClick={() => navigate(`/user-verifications/user/${row.id}`)}
                            >
                              <BadgeCheck className="h-3.5 w-3.5 text-[#00a3ff]" />
                            </button>
                          ) : null}
                        </span>
                      </td>
                      <td className="px-4 py-3">{row.last_name || '-'}</td>
                      <td className="px-4 py-3">{row.email || '-'}</td>
                      <td className="px-4 py-3">
                        <span dir="ltr" className="inline-block">
                          {`${row.country_code || ''} ${row.phone || ''}`.trim() || '-'}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <select
                          value={row.status}
                          onChange={(e) => updateStatus(row, e.target.value as UserStatus)}
                          className={`h-9 min-w-[120px] rounded-full border px-3 text-xs font-semibold shadow-sm outline-none transition-all focus:ring-2 focus:ring-[#7367f0]/30 ${statusSelectClass(row.status)}`}
                        >
                          <option value="1">{statusLabel['1']}</option>
                          <option value="2">{statusLabel['2']}</option>
                          <option value="3">{statusLabel['3']}</option>
                        </select>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <Link to={`/users/${row.id}`}>
                            <Button size="icon" variant="secondary" title={t.details}>
                              <Eye className="h-4 w-4" />
                            </Button>
                          </Link>
                          <Button size="icon" variant="secondary" title={t.edit} onClick={() => openEdit(row)}>
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Link to={`/users/${row.id}/devices`}>
                            <Button size="icon" variant="secondary" title={t.deviceSessions}>
                              <Smartphone className="h-4 w-4" />
                            </Button>
                          </Link>
                          <Link to={`/users/${row.id}/favorites`}>
                            <Button size="icon" variant="secondary" title={t.favoriteAds}>
                              <Heart className="h-4 w-4" />
                            </Button>
                          </Link>
                          <Button size="icon" variant="destructive" title={t.delete} onClick={() => setDeletingUser(row)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
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

      {creatingUser ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/30 p-4 backdrop-blur-[1px] animate-in fade-in duration-200">
          <Card className="w-full max-w-2xl shadow-2xl animate-in zoom-in-95 duration-200">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{t.createUser}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setCreatingUser(false)}>
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 md:grid-cols-2">
                <Field label={t.firstName}>
                  <Input value={createData.first_name || ''} onChange={(e) => setCreateData((s) => ({ ...s, first_name: e.target.value }))} />
                </Field>
                <Field label={t.lastName}>
                  <Input value={createData.last_name || ''} onChange={(e) => setCreateData((s) => ({ ...s, last_name: e.target.value }))} />
                </Field>
                <Field label={t.email}>
                  <Input type="email" value={createData.email || ''} onChange={(e) => setCreateData((s) => ({ ...s, email: e.target.value }))} />
                </Field>
                <Field label={t.phone}>
                  <Input value={createData.phone || ''} onChange={(e) => setCreateData((s) => ({ ...s, phone: e.target.value }))} />
                </Field>
                <Field label={t.countryCode}>
                  <Input value={createData.country_code || ''} onChange={(e) => setCreateData((s) => ({ ...s, country_code: e.target.value }))} />
                </Field>
                <Field label={t.status}>
                  <select
                    value={createData.status || '1'}
                    onChange={(e) => setCreateData((s) => ({ ...s, status: e.target.value }))}
                    className="h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
                  >
                    <option value="1">{t.active}</option>
                    <option value="2">{t.pending}</option>
                    <option value="3">{t.disabled}</option>
                  </select>
                </Field>
                <Field label={t.password}>
                  <Input type="password" value={createData.password || ''} onChange={(e) => setCreateData((s) => ({ ...s, password: e.target.value }))} />
                </Field>
                <Field label={t.confirmPassword}>
                  <Input type="password" value={createData.password_confirmation || ''} onChange={(e) => setCreateData((s) => ({ ...s, password_confirmation: e.target.value }))} />
                </Field>
              </div>

              <div className="mt-5 flex items-center gap-2">
                <Button onClick={submitCreate} disabled={saving}>{t.save}</Button>
                <Button variant="secondary" onClick={() => setCreatingUser(false)}>{t.cancel}</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}

      {editingUser ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/30 p-4 backdrop-blur-[1px] animate-in fade-in duration-200">
          <Card className="w-full max-w-2xl shadow-2xl animate-in zoom-in-95 duration-200">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>
                {t.edit} #{editingUser.id}
              </CardTitle>
              <Button variant="ghost" size="icon" onClick={() => setEditingUser(null)}>
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 md:grid-cols-2">
                <Input
                  placeholder={t.firstName}
                  value={formData.first_name || ''}
                  onChange={(e) => setFormData((s) => ({ ...s, first_name: e.target.value }))}
                />
                <Input
                  placeholder={t.lastName}
                  value={formData.last_name || ''}
                  onChange={(e) => setFormData((s) => ({ ...s, last_name: e.target.value }))}
                />
                <Input
                  placeholder={t.email}
                  value={formData.email || ''}
                  onChange={(e) => setFormData((s) => ({ ...s, email: e.target.value }))}
                />
                <Input
                  placeholder={t.phone}
                  value={formData.phone || ''}
                  onChange={(e) => setFormData((s) => ({ ...s, phone: e.target.value }))}
                />
                <Input
                  placeholder={t.countryCode}
                  value={formData.country_code || ''}
                  onChange={(e) => setFormData((s) => ({ ...s, country_code: e.target.value }))}
                />
                <select
                  value={formData.status || '1'}
                  onChange={(e) => setFormData((s) => ({ ...s, status: e.target.value }))}
                  className="h-10 rounded-xl border border-[#dbdbe8] bg-white px-3 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
                >
                  <option value="1">{t.active}</option>
                  <option value="2">{t.pending}</option>
                  <option value="3">{t.disabled}</option>
                </select>
              </div>

              <div className="mt-5 flex items-center gap-2">
                <Button onClick={submitEdit} disabled={saving}>
                  {t.save}
                </Button>
                <Button variant="secondary" onClick={() => setEditingUser(null)}>
                  {t.cancel}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}

      {deletingUser ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/35 p-4 backdrop-blur-[1px] animate-in fade-in duration-200">
          <Card className="w-full max-w-md shadow-2xl animate-in zoom-in-95 duration-200">
            <CardHeader>
              <CardTitle>{t.confirmDeletion}</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="mb-4 text-sm text-[#6f6b7d] dark:text-[#b6b8cc]">
                {t.deleteUserConfirmation.replace('{name}', userDisplayName(deletingUser))}
              </p>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setDeletingUser(null)}>
                  {t.cancel}
                </Button>
                <Button variant="destructive" onClick={confirmDelete} disabled={saving}>
                  {t.delete}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </div>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="space-y-1.5 text-sm">
      <span className="font-medium text-[#5d596c] dark:text-[#d7d8ea]">{label}</span>
      {children}
    </label>
  );
}

function userDisplayName(user: UserRow): string {
  return [user.first_name, user.last_name].filter(Boolean).join(' ') || user.name || user.email || `#${user.id}`;
}
