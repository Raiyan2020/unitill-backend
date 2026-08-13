import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { api } from '../lib/api';
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

export function UserDetailsPage() {
  const { id } = useParams();
  const { t, locale } = useI18n();
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<UserDetails | null>(null);
  const [actions, setActions] = useState<ModerationAction[]>([]);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/admin/users/${id}`);
        setUser(res?.data?.data || null);
        const history = await api.get(`/admin/users/${id}/moderation-actions`, { params: { per_page: 100 } });
        setActions(history?.data?.data?.data || []);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [id]);

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
              <Item label={locale === 'ar' ? 'عدد التحذيرات' : 'Warnings'} value={String(user.warning_count || 0)} />
              <Item label={locale === 'ar' ? 'موقوف حتى' : 'Suspended until'} value={user.suspended_until ? formatDateTime(user.suspended_until, locale) : '-'} />
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{locale === 'ar' ? 'سجل قرارات الإشراف' : 'Moderation Decision Log'}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {actions.map((action) => (
            <div key={action.id} className="rounded-lg border border-slate-200 p-3 dark:border-[#44485f]">
              <div className="flex flex-wrap justify-between gap-2"><strong>{action.action.replaceAll('_', ' ')}</strong><span className="text-xs text-slate-500">{formatDateTime(action.created_at, locale)}</span></div>
              <p className="mt-2 text-sm">{action.reason}</p>
              <p className="mt-1 text-xs text-slate-500">{action.admin?.name || 'System'}{action.ends_at ? ` · until ${formatDateTime(action.ends_at, locale)}` : ''}</p>
            </div>
          ))}
          {!actions.length ? <p className="text-sm text-slate-500">{locale === 'ar' ? 'لا توجد قرارات مسجلة' : 'No moderation decisions recorded'}</p> : null}
        </CardContent>
      </Card>
    </div>
  );
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
