import { Edit, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type RoleRow = { id: number; name: string; permissions: string[] };
type PermissionRow = { id: number; name: string };

export function RolesPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<RoleRow[]>([]);
  const [permissions, setPermissions] = useState<PermissionRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [editing, setEditing] = useState<RoleRow | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [deleting, setDeleting] = useState<RoleRow | null>(null);
  const [form, setForm] = useState({ name: '', permissions: [] as string[] });
  const [saving, setSaving] = useState(false);

  const fetchAll = async () => {
    setLoading(true);
    try {
      const [rolesRes, permsRes] = await Promise.all([api.get('/admin/roles'), api.get('/admin/permissions')]);
      setRows(ensureApiSuccess<RoleRow[]>(rolesRes, 'Failed to load roles') || []);
      setPermissions(ensureApiSuccess<PermissionRow[]>(permsRes, 'Failed to load permissions') || []);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load roles.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAll();
  }, []);

  const startCreate = () => {
    setFormOpen(true);
    setEditing(null);
    setForm({ name: '', permissions: [] });
  };

  const startEdit = (row: RoleRow) => {
    setFormOpen(true);
    setEditing(row);
    setForm({ name: row.name, permissions: row.permissions || [] });
  };

  const save = async () => {
    setSaving(true);
    try {
      if (editing) {
        const res = await api.put(`/admin/roles/${editing.id}`, form);
        ensureApiSuccess(res, 'Failed to update role');
      } else {
        const res = await api.post('/admin/roles', form);
        ensureApiSuccess(res, 'Failed to create role');
      }
      setFormOpen(false);
      setEditing(null);
      setForm({ name: '', permissions: [] });
      await fetchAll();
      notify.success(editing ? 'Role updated successfully.' : 'Role created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update role.' : 'Failed to create role.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/roles/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete role');
      setDeleting(null);
      await fetchAll();
      notify.success('Role deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete role.');
    } finally {
      setSaving(false);
    }
  };

  const filteredRows = rows.filter((row) => row.name.toLowerCase().includes(search.toLowerCase()));
  const total = filteredRows.length;
  const pagesCount = Math.max(1, Math.ceil(total / pageSize));
  const paginatedRows = filteredRows.slice((page - 1) * pageSize, page * pageSize);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.roles}</h2>
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
              <option key={size} value={size}>{size}</option>
            ))}
          </select>
          <Input
            className="h-9 w-[220px]"
            placeholder="Search"
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
          />
          <Button size="sm" onClick={startCreate}>+ Add Role</Button>
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
                  <th className="px-4 py-3 text-start">{t.permissions}</th>
                  <th className="px-4 py-3 text-start">{t.actions}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={4} />
                ) : paginatedRows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={4}>{t.noDataFound}</td></tr>
                ) : paginatedRows.map((row) => (
                  <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                    <td className="px-4 py-3">{row.id}</td>
                    <td className="px-4 py-3">{row.name}</td>
                    <td className="px-4 py-3">
                      {row.permissions?.length ? (
                        <div className="flex flex-wrap gap-2">
                          {row.permissions.map((perm) => (
                            <span
                              key={perm}
                              className="inline-flex items-center rounded-full border border-[#7367f0]/40 bg-[#7367f0]/15 px-2.5 py-1 text-xs font-medium text-[#5b4ff0] dark:border-[#8f84ff]/45 dark:bg-[#7367f0]/25 dark:text-[#cdc8ff]"
                            >
                              {perm}
                            </span>
                          ))}
                        </div>
                      ) : (
                        '-'
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <Button size="icon" variant="secondary" onClick={() => startEdit(row)}><Edit className="h-4 w-4" /></Button>
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
              <CardTitle>{editing ? `Edit role #${editing.id}` : 'Create role'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => { setEditing(null); setFormOpen(false); setForm({ name: '', permissions: [] }); }}>
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent className="space-y-3">
              <Input placeholder="Role name" value={form.name} onChange={(e) => setForm((s) => ({ ...s, name: e.target.value }))} />
              <div className="flex max-h-[300px] flex-wrap gap-2 overflow-auto rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
                {permissions.map((perm) => (
                  <label
                    key={perm.id}
                    className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-all ${
                      form.permissions.includes(perm.name)
                        ? 'border-[#7367f0] bg-[#7367f0]/15 text-[#4d41ce] dark:border-[#8f84ff] dark:bg-[#7367f0]/25 dark:text-[#c9c4ff]'
                        : 'border-[#dfe0ea] bg-white text-[#5f6378] hover:bg-[#f7f7fc] dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#b6b8cc] dark:hover:bg-[#383d56]'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={form.permissions.includes(perm.name)}
                      onChange={(e) =>
                        setForm((s) => ({
                          ...s,
                          permissions: e.target.checked ? [...s.permissions, perm.name] : s.permissions.filter((p) => p !== perm.name),
                        }))
                      }
                      className="h-4 w-4 rounded-full accent-[#7367f0]"
                    />
                    {perm.name}
                  </label>
                ))}
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => { setEditing(null); setFormOpen(false); setForm({ name: '', permissions: [] }); }}>{t.cancel}</Button>
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
              <p className="mb-4 text-sm">Delete role #{deleting.id}?</p>
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
