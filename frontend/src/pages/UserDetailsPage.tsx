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
};

export function UserDetailsPage() {
  const { id } = useParams();
  const { t, locale } = useI18n();
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<UserDetails | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get(`/admin/users/${id}`);
        setUser(res?.data?.data || null);
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
            </div>
          )}
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
