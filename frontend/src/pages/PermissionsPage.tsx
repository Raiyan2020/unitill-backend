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

type PermissionRow = {
  id: number;
  name: string;
};

export function PermissionsPage() {
  const notify = useNotify();
  const { t } = useI18n();
  const [rows, setRows] = useState<PermissionRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [editing, setEditing] = useState<PermissionRow | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [deleting, setDeleting] = useState<PermissionRow | null>(null);
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);

  const fetchPermissions = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/permissions');
      setRows(ensureApiSuccess<PermissionRow[]>(res, 'Failed to load permissions') || []);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load permissions.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPermissions();
  }, []);

  const startCreate = () => {
    setFormOpen(true);
    setEditing(null);
    setName('');
  };

  const startEdit = (row: PermissionRow) => {
    setFormOpen(true);
    setEditing(row);
    setName(row.name);
  };

  const save = async () => {
    if (!name.trim()) {
      notify.error('Permission name is required.');
      return;
    }
    setSaving(true);
    try {
      if (editing) {
        const res = await api.put(`/admin/permissions/${editing.id}`, { name });
        ensureApiSuccess(res, 'Failed to update permission');
      } else {
        const res = await api.post('/admin/permissions', { name });
        ensureApiSuccess(res, 'Failed to create permission');
      }
      setEditing(null);
      setFormOpen(false);
      setName('');
      await fetchPermissions();
      notify.success(editing ? 'Permission updated successfully.' : 'Permission created successfully.');
    } catch (error) {
      notify.errorFrom(error, editing ? 'Failed to update permission.' : 'Failed to create permission.');
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    setSaving(true);
    try {
      const res = await api.delete(`/admin/permissions/${deleting.id}`);
      ensureApiSuccess(res, 'Failed to delete permission');
      setDeleting(null);
      await fetchPermissions();
      notify.success('Permission deleted successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to delete permission.');
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
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.permissions}</h2>
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
            placeholder={t.search}
            value={search}
            onChange={(e) => {
              setPage(1);
              setSearch(e.target.value);
            }}
          />
          <Button size="sm" onClick={startCreate}>+ Add Permission</Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <table className="w-full text-sm">
            <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
              <tr>
                <th className="px-4 py-3 text-start">{t.id}</th>
                <th className="px-4 py-3 text-start">{t.name}</th>
                <th className="px-4 py-3 text-start">{t.actions}</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <TableLoadingRow colSpan={3} />
              ) : paginatedRows.length === 0 ? (
                <tr><td className="px-4 py-6 text-center text-sm text-[#8a8da8]" colSpan={3}>{t.noDataFound}</td></tr>
              ) : paginatedRows.map((row) => (
                <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                  <td className="px-4 py-3">{row.id}</td>
                  <td className="px-4 py-3">{row.name}</td>
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
          <Card className="w-full max-w-lg">
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{editing ? `Edit permission #${editing.id}` : 'Create permission'}</CardTitle>
              <Button variant="ghost" size="icon" onClick={() => { setEditing(null); setFormOpen(false); setName(''); }}>
                <X className="h-4 w-4" />
              </Button>
            </CardHeader>
            <CardContent className="space-y-3">
              <Input placeholder={t.permissionName} value={name} onChange={(e) => setName(e.target.value)} />
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => { setEditing(null); setFormOpen(false); setName(''); }}>{t.cancel}</Button>
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
              <p className="mb-4 text-sm">Delete permission #{deleting.id}?</p>
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
