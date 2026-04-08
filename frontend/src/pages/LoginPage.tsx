import { Loader2, Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { setAuthToken } from '../lib/auth';
import { useI18n } from '../providers/i18n-provider';

export function LoginPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [email, setEmail] = useState('admin@admin.net');
  const [password, setPassword] = useState('123456');
  const navigate = useNavigate();
  const { t } = useI18n();

  const onSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const res = await api.post('/admin/login', { email, password });
      const token = res?.data?.data?.token;
      if (!token) throw new Error('Token not found');
      setAuthToken(token);
      navigate('/', { replace: true });
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-100 p-4">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>{t.appName}</CardTitle>
          <CardDescription>{t.signInSubtitle}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            {error ? <p className="rounded-lg bg-red-50 p-2 text-sm text-red-600">{error}</p> : null}

            <div className="space-y-2">
              <label className="text-sm font-medium text-slate-700">{t.email}</label>
              <div className="relative">
                <Mail className="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input className="ps-9" value={email} onChange={(e) => setEmail(e.target.value)} />
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-slate-700">{t.password}</label>
              <div className="relative">
                <Lock className="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  type="password"
                  className="ps-9"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </div>
            </div>

            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              <span className="ms-1">{t.login}</span>
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
