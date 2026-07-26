import { BadgeCheck, Edit, Eye, Heart, Smartphone, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
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
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<UserRow[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);

  const [editingUser, setEditingUser] = useState<UserRow | null>(null);
  const [deletingUser, setDeletingUser] = useState<UserRow | null>(null);
  const [formData, setFormData] = useState<Record<string, string>>({});
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

  const submitEdit = async () => {
    if (!editingUser) return;

    setSaving(true);
    try {
      await api.put(`/admin/users/${editingUser.id}`, formData);
      setEditingUser(null);
      await fetchUsers();
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
    } finally {
      setSaving(false);
    }
  };

  const updateStatus = async (user: UserRow, status: UserStatus) => {
    await api.put(`/admin/users/${user.id}`, { status });
    await fetchUsers();
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));
  const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const end = Math.min(page * pageSize, total);

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
          <p className="text-sm text-[#8a8da8] dark:text-[#a2a5be]">Manage users and basic profile data</p>
        </div>

        <div className="ms-auto flex w-full max-w-[520px] items-center justify-end gap-2">
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
                    <td className="px-4 py-5 text-[#8a8da8]" colSpan={7}>
                      No users found.
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
                              title="View verification details"
                              onClick={() => navigate(`/user-verifications/user/${row.id}`)}
                            >
                              <BadgeCheck className="h-3.5 w-3.5 text-[#00a3ff]" />
                            </button>
                          ) : null}
                        </span>
                      </td>
                      <td className="px-4 py-3">{row.last_name || '-'}</td>
                      <td className="px-4 py-3">{row.email || '-'}</td>
                      <td className="px-4 py-3">{`${row.country_code || ''} ${row.phone || ''}`.trim() || '-'}</td>
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
                            <Button size="icon" variant="secondary" title="Device sessions">
                              <Smartphone className="h-4 w-4" />
                            </Button>
                          </Link>
                          <Link to={`/users/${row.id}/favorites`}>
                            <Button size="icon" variant="secondary" title="Favorite ads">
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

          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#ececf3] px-4 py-3 text-xs text-[#8a8da8] dark:border-[#44485f] dark:text-[#a2a5be]">
            <p>
              Showing {start} to {end} of {total} entries
            </p>
            <div className="flex gap-2">
              <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Prev
              </Button>
              <span className="inline-flex items-center px-2 text-sm">{page}</span>
              <Button variant="secondary" size="sm" disabled={page >= pagesCount} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

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
              <CardTitle>Confirm deletion</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="mb-4 text-sm text-[#6f6b7d] dark:text-[#b6b8cc]">
                Are you sure you want to delete user #{deletingUser.id}? This action cannot be undone.
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
