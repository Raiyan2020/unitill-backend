import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { api } from '../lib/api';
import { hasPermission } from '../lib/auth';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type UserDetails = {
  id: number;
  name: string | null;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  country_code: string | null;
  city_id: number | null;
  status: '1' | '2' | '3';
  created_at: string;
  warning_count: number;
  suspended_until: string | null;
};

type ModerationAction = {
  id: number;
  action: string;
  reason: string;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
  admin: { name: string; email: string } | null;
};

type FeatureRestriction = {
  id: number;
  feature: 'posting' | 'messaging';
  reason: string;
  starts_at: string;
  ends_at: string | null;
  lifted_at: string | null;
  admin: { name: string; email: string } | null;
};

export function UserDetailsPage() {
  const { id } = useParams();
  const { t, locale } = useI18n();
  const notify = useNotify();
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<UserDetails | null>(null);
  const [actions, setActions] = useState<ModerationAction[]>([]);
  const [restrictions, setRestrictions] = useState<FeatureRestriction[]>([]);
  const [restrictionForm, setRestrictionForm] = useState({ feature: 'posting' as 'posting' | 'messaging', reason: '', duration_days: '' });
  const canManageRestrictions = hasPermission('users.update');

  const loadRestrictions = async () => {
    const res = await api.get(`/admin/users/${id}/feature-restrictions`);
    setRestrictions(res?.data?.data || []);
  };

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/admin/users/${id}`);
        setUser(res?.data?.data || null);
        const history = await api.get(`/admin/users/${id}/moderation-actions`, { params: { per_page: 100 } });
        setActions(history?.data?.data?.data || []);
        await loadRestrictions();
      } finally {
        setLoading(false);
      }
    };

    load();
  // The route id is the lifecycle boundary for all user-detail requests.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const addRestriction = async () => {
    if (!restrictionForm.reason.trim()) {
      notify.error(t.reasonRequired);
      return;
    }
    try {
      await api.post(`/admin/users/${id}/feature-restrictions`, {
        feature: restrictionForm.feature,
        reason: restrictionForm.reason.trim(),
        duration_days: restrictionForm.duration_days ? Number(restrictionForm.duration_days) : null,
      });
      setRestrictionForm({ feature: 'posting', reason: '', duration_days: '' });
      notify.success(t.featureRestrictionApplied);
      await loadRestrictions();
    } catch (error) { notify.errorFrom(error, t.actionFailed); }
  };

  const liftRestriction = async (restriction: FeatureRestriction) => {
    if (!window.confirm(t.liftRestrictionConfirmation)) return;
    const reason = window.prompt(t.liftReason) || '';
    try {
      await api.delete(`/admin/users/${id}/feature-restrictions/${restriction.id}`, { data: { reason: reason || null } });
      notify.success(t.featureRestrictionLifted);
      await loadRestrictions();
    } catch (error) { notify.errorFrom(error, t.actionFailed); }
  };

  const statusText = user?.status === '1' ? t.active : user?.status === '2' ? t.pending : t.disabled;
  const statusVariant = user?.status === '1' ? 'success' : user?.status === '2' ? 'warning' : 'destructive';

  return (
    <div className="space-y-4">
      <Link to="/users">
        <Button variant="secondary" size="sm">
          <ArrowLeft className="me-1 h-4 w-4" />
          {t.backToUsers}
        </Button>
      </Link>

      <Card>
        <CardHeader>
          <CardTitle>{t.userDetails}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-slate-500">{t.loading}</p>
          ) : !user ? (
            <p className="text-sm text-slate-500">{t.userNotFound}</p>
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
              <Item label={t.id} value={String(user.id)} />
              <Item label={t.name} value={user.name || '-'} />
              <Item label={t.firstName} value={user.first_name || '-'} />
              <Item label={t.lastName} value={user.last_name || '-'} />
              <Item label={t.email} value={user.email || '-'} />
              <Item label={t.phone} value={`${user.country_code || ''} ${user.phone || ''}`.trim() || '-'} />
              <Item label={t.cityId} value={user.city_id?.toString() || '-'} />
              <div>
                <p className="mb-1 text-xs text-slate-500">{t.status}</p>
                <Badge variant={statusVariant}>{statusText}</Badge>
              </div>
              <Item label={t.createdAt} value={formatDateTime(user.created_at, locale)} />
              <Item label={t.warnings} value={String(user.warning_count || 0)} />
              <Item label={t.suspendedUntil} value={user.suspended_until ? formatDateTime(user.suspended_until, locale) : '-'} />
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{t.featureRestrictions}</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          {canManageRestrictions ? <div className="grid gap-2 rounded-xl border border-slate-200 p-3 dark:border-[#44485f] md:grid-cols-[160px_1fr_160px_auto]">
            <select className="h-10 rounded-lg border bg-white px-3 text-sm dark:bg-[#2f3349]" value={restrictionForm.feature} onChange={(e) => setRestrictionForm((s) => ({ ...s, feature: e.target.value as 'posting' | 'messaging' }))}><option value="posting">{t.posting}</option><option value="messaging">{t.messaging}</option></select>
            <input className="h-10 rounded-lg border px-3 text-sm dark:bg-[#2f3349]" placeholder={t.restrictionReason} value={restrictionForm.reason} onChange={(e) => setRestrictionForm((s) => ({ ...s, reason: e.target.value }))} />
            <input className="h-10 rounded-lg border px-3 text-sm dark:bg-[#2f3349]" type="number" min="1" max="365" placeholder={t.durationDaysOptional} value={restrictionForm.duration_days} onChange={(e) => setRestrictionForm((s) => ({ ...s, duration_days: e.target.value }))} />
            <Button onClick={addRestriction}>{t.applyRestriction}</Button>
          </div> : null}
          {restrictions.map((restriction) => <div key={restriction.id} className="rounded-lg border border-slate-200 p-3 dark:border-[#44485f]"><div className="flex flex-wrap items-center justify-between gap-2"><strong>{restriction.feature === 'posting' ? t.posting : t.messaging}</strong><span className={`rounded-full px-2 py-1 text-xs ${restriction.lifted_at ? 'bg-slate-500/15' : 'bg-red-500/15 text-red-700'}`}>{restriction.lifted_at ? t.lifted : t.restricted}</span></div><p className="mt-2 text-sm">{restriction.reason}</p><p className="mt-1 text-xs text-slate-500">{restriction.admin?.name || t.systemLabel} · {formatDateTime(restriction.starts_at, locale)}{restriction.ends_at ? ` · ${t.untilLabel} ${formatDateTime(restriction.ends_at, locale)}` : ''}</p>{canManageRestrictions && !restriction.lifted_at ? <Button className="mt-3" size="sm" variant="secondary" onClick={() => liftRestriction(restriction)}>{t.liftRestriction}</Button> : null}</div>)}
          {!restrictions.length ? <p className="text-sm text-slate-500">{t.noFeatureRestrictions}</p> : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{t.moderationDecisionLog}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {actions.map((action) => (
            <div key={action.id} className="rounded-lg border border-slate-200 p-3 dark:border-[#44485f]">
              <div className="flex flex-wrap justify-between gap-2"><strong>{moderationActionLabel(action.action, t)}</strong><span className="text-xs text-slate-500">{formatDateTime(action.created_at, locale)}</span></div>
              <p className="mt-2 text-sm">{action.reason}</p>
              <p className="mt-1 text-xs text-slate-500">{action.admin?.name || t.systemLabel}{action.ends_at ? ` · ${t.untilLabel} ${formatDateTime(action.ends_at, locale)}` : ''}</p>
            </div>
          ))}
          {!actions.length ? <p className="text-sm text-slate-500">{t.noModerationDecisions}</p> : null}
        </CardContent>
      </Card>
    </div>
  );
}

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

function formatDateTime(value: string, locale: 'en' | 'ar'): string {
  if (!value) return '-';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

function Item({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="mb-1 text-xs text-slate-500">{label}</p>
      <p className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900">{value}</p>
    </div>
  );
}
