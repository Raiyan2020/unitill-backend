import { useEffect, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type Appeal = {
  id: number;
  message: string;
  status: 'pending' | 'accepted' | 'rejected';
  decision_reason: string | null;
  created_at: string;
  user: { id: number; name: string; email: string };
  action: { id: number; action: string; reason: string; ends_at: string | null };
};

function moderationActionLabel(action: string, t: ReturnType<typeof useI18n>['t']): string {
  switch (action) {
    case 'warning': return t.modWarning;
    case 'temporary_suspension': return t.modTemporarySuspension;
    case 'permanent_suspension': return t.modPermanentSuspension;
    case 'reactivated': return t.modReactivated;
    case 'suspension_expired': return t.modSuspensionExpired;
    default: return action.replaceAll('_', ' ');
  }
}

function appealStatusLabel(status: string, t: ReturnType<typeof useI18n>['t']): string {
  switch (status) {
    case 'pending': return t.appealStatusPending;
    case 'accepted': return t.appealStatusAccepted;
    case 'rejected': return t.appealStatusRejected;
    default: return status;
  }
}

export function ModerationAppealsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<Appeal[]>([]);
  const [status, setStatus] = useState('pending');

  const load = async () => {
    try {
      const res = await api.get('/admin/moderation-appeals', { params: { status, per_page: 100 } });
      const payload = ensureApiSuccess<{ data: Appeal[] }>(res, t.unableToLoadAppeals);
      setRows(payload?.data || []);
    } catch (error) { notify.errorFrom(error, t.unableToLoadAppeals); }
  };

  useEffect(() => { load(); }, [status]);

  const decide = async (appeal: Appeal, decision: 'accepted' | 'rejected') => {
    const reason = window.prompt(t.decisionReason);
    if (!reason?.trim()) return;
    try {
      await api.put(`/admin/moderation-appeals/${appeal.id}`, { status: decision, decision_reason: reason.trim() });
      notify.success(t.decisionRecorded);
      await load();
    } catch (error) { notify.errorFrom(error, t.unableToSaveDecision); }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold">{t.moderationAppeals}</h2>
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="h-9 rounded-lg border bg-white px-3 text-sm dark:bg-[#2f3349]">
          <option value="pending">{t.appealStatusPending}</option><option value="accepted">{t.appealStatusAccepted}</option><option value="rejected">{t.appealStatusRejected}</option><option value="">{t.appealStatusAll}</option>
        </select>
      </div>
      {rows.map((appeal) => (
        <Card key={appeal.id}><CardContent className="space-y-3 p-4">
          <div className="flex flex-wrap justify-between gap-2">
            <div><p className="font-semibold">#{appeal.id} — {appeal.user?.name || appeal.user?.email}</p><p className="text-xs text-[#8a8da8]">{moderationActionLabel(appeal.action?.action, t)} · {appeal.created_at}</p></div>
            <span className="rounded-full bg-slate-500/15 px-2 py-1 text-xs font-semibold">{appealStatusLabel(appeal.status, t)}</span>
          </div>
          <div className="rounded-lg bg-[#f8f7fb] p-3 text-sm dark:bg-[#383d56]"><strong>{t.decisionLabel}:</strong> {appeal.action?.reason}</div>
          <div className="rounded-lg border p-3 text-sm"><strong>{t.appealLabel}:</strong> {appeal.message}</div>
          {appeal.status === 'pending' ? <div className="flex gap-2"><Button onClick={() => decide(appeal, 'accepted')}>{t.accept}</Button><Button variant="secondary" onClick={() => decide(appeal, 'rejected')}>{t.reject}</Button></div> : <p className="text-sm">{appeal.decision_reason}</p>}
        </CardContent></Card>
      ))}
      {!rows.length ? <Card><CardContent className="p-8 text-center text-sm text-[#8a8da8]">{t.noAppealsFound}</CardContent></Card> : null}
    </div>
  );
}
