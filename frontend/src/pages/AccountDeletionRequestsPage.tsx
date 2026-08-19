import { useEffect, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { api } from '../lib/api';
import { hasPermission } from '../lib/auth';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type DeletionRequest = {
  id: number;
  email: string;
  reason: string | null;
  status: 'pending' | 'completed' | 'rejected';
  requested_at: string;
  resolved_at: string | null;
  user: { id: number; name: string; email: string } | null;
};
type Paginator = { data: DeletionRequest[] };

export function AccountDeletionRequestsPage() {
  const { t, locale } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<DeletionRequest[]>([]);
  const [status, setStatus] = useState('pending');
  const [loading, setLoading] = useState(true);
  const canResolve = hasPermission('users.delete');

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/account-deletion-requests', { params: { status: status || undefined, per_page: 100 } });
      setRows(ensureApiSuccess<Paginator>(res, t.actionFailed)?.data || []);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally { setLoading(false); }
  };
  // Reload only when the selected server-side status filter changes.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { load(); }, [status]);

  const resolve = async (row: DeletionRequest, decision: 'completed' | 'rejected') => {
    const warning = decision === 'completed' ? t.completeDeletionWarning : t.rejectDeletionConfirmation;
    if (!window.confirm(warning)) return;
    const note = window.prompt(t.resolutionNote) || '';
    try {
      await api.put(`/admin/account-deletion-requests/${row.id}`, { status: decision, resolution_note: note || null });
      notify.success(t.requestResolved);
      await load();
    } catch (error) { notify.errorFrom(error, t.actionFailed); }
  };

  return <div className="space-y-4">
    <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-2xl font-semibold">{t.accountDeletionRequests}</h2><p className="text-sm text-[#8a8da8]">{t.accountDeletionSubtitle}</p></div><select className="h-9 rounded-lg border bg-white px-3 text-sm dark:bg-[#2f3349]" value={status} onChange={(e) => setStatus(e.target.value)}><option value="pending">{t.pending}</option><option value="completed">{t.completed}</option><option value="rejected">{t.rejected}</option><option value="">{t.allStatuses}</option></select></div>
    {loading ? <Card><CardContent className="p-8 text-center">{t.loading}</CardContent></Card> : rows.map((row) => <Card key={row.id}><CardContent className="space-y-3 p-4">
      <div className="flex flex-wrap justify-between gap-3"><div><p className="font-semibold">#{row.id} — {row.user?.name || row.email}</p><p className="text-sm text-[#8a8da8]">{row.email} · {new Date(row.requested_at).toLocaleString(locale)}</p></div><span className="h-fit rounded-full bg-slate-500/15 px-2 py-1 text-xs font-semibold">{row.status}</span></div>
      <p className="rounded-lg bg-[#f8f7fb] p-3 text-sm dark:bg-[#383d56]">{row.reason || t.noReasonProvided}</p>
      {canResolve && row.status === 'pending' ? <div className="flex gap-2"><Button variant="destructive" onClick={() => resolve(row, 'completed')}>{t.completePermanentDeletion}</Button><Button variant="secondary" onClick={() => resolve(row, 'rejected')}>{t.reject}</Button></div> : null}
    </CardContent></Card>)}
    {!loading && !rows.length ? <Card><CardContent className="p-8 text-center text-sm text-[#8a8da8]">{t.noDeletionRequests}</CardContent></Card> : null}
  </div>;
}
