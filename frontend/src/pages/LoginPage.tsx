import { Eye, EyeOff, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { setAdminAuthInfo, setAuthToken, type AdminAuthInfo } from '../lib/auth';
import { useI18n } from '../providers/i18n-provider';

export function LoginPage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [email, setEmail] = useState('admin@admin.net');
  const [password, setPassword] = useState('123456');
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  const { t } = useI18n();

  const onSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const res = await api.post('/admin/login', { email, password });
      const token = res?.data?.data?.token;
      const admin = res?.data?.data?.admin as AdminAuthInfo | undefined;
      if (!token) throw new Error('Token not found');
      if (!admin) throw new Error('Admin data not found');
      setAuthToken(token);
      setAdminAuthInfo(admin);
      navigate('/', { replace: true });
    } catch (err: any) {
      setError(err?.response?.data?.message || t.loginFailed);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#2b2f4b] text-[#cfd3ef]">
      <div className="mx-auto grid min-h-screen max-w-[1440px] grid-cols-1 lg:grid-cols-[1.45fr_1fr]">
        <section className="relative hidden overflow-hidden border-r border-white/5 lg:block">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_35%_35%,rgba(115,103,240,0.22),transparent_52%)]" />
          <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.02),transparent)]" />

          <div className="relative flex h-full items-center justify-center px-10">
            <div className="relative h-[460px] w-[560px]">
              <div className="absolute start-6 top-10 w-32 rounded-2xl border border-[#3d4263] bg-[#2f3453] p-4 shadow-[0_14px_24px_rgba(0,0,0,0.25)]">
                <p className="text-xs text-[#a9aed3]">{t.profit}</p>
                <p className="mt-3 text-lg font-semibold text-white">624k</p>
              </div>
              <div className="absolute end-10 top-28 w-32 rounded-2xl border border-[#3d4263] bg-[#2f3453] p-4 shadow-[0_14px_24px_rgba(0,0,0,0.25)]">
                <p className="text-xs text-[#a9aed3]">{t.order}</p>
                <p className="mt-3 text-lg font-semibold text-white">124k</p>
              </div>
              <div className="absolute inset-x-0 bottom-0 mx-auto flex h-[300px] w-[300px] items-center justify-center rounded-full border border-[#3b4060] bg-[#2b304d] text-8xl shadow-[0_20px_50px_rgba(0,0,0,0.35)]">
                <span aria-hidden>🧑</span>
              </div>
            </div>
          </div>
        </section>

        <section className="flex items-center justify-center px-5 py-8 sm:px-8 lg:px-12">
          <div className="w-full max-w-[440px]">
            <h1 className="mb-1 text-3xl font-semibold text-white">{t.welcomeToDashboard} 👋</h1>
            <p className="mb-7 text-sm text-[#a8add3]">{t.signInSubtitle}</p>

            <form onSubmit={onSubmit} className="space-y-4">
              {error ? <p className="rounded-lg border border-[#ff4c51]/40 bg-[#ff4c51]/10 px-3 py-2 text-sm text-[#ff9ca0]">{error}</p> : null}

              <div className="space-y-1.5">
                <label className="text-sm text-[#b8bcde]">{t.email} or Username</label>
                <Input
                  className="h-11 rounded-lg border-[#444a6c] bg-[#2f3453] text-[#d7d9ee] placeholder:text-[#8f95bc] focus-visible:ring-[#7367f0]"
                  placeholder={t.enterEmailOrUsername}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-sm text-[#b8bcde]">{t.password}</label>
                <div className="relative">
                  <Input
                    type={showPassword ? 'text' : 'password'}
                    className="h-11 rounded-lg border-[#444a6c] bg-[#2f3453] pe-10 text-[#d7d9ee] placeholder:text-[#8f95bc] focus-visible:ring-[#7367f0]"
                    placeholder=".........."
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((s) => !s)}
                    className="absolute end-3 top-1/2 -translate-y-1/2 text-[#8f95bc] hover:text-[#cfd3ef]"
                    aria-label={t.togglePassword}
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              <div className="flex items-center justify-between text-sm">
                <label className="inline-flex items-center gap-2 text-[#b8bcde]">
                  <input type="checkbox" className="h-4 w-4 accent-[#7367f0]" />
                  {t.rememberMe}
                </label>
                <button type="button" className="text-[#8f86ff] hover:text-[#a9a2ff]">
                  {t.forgotPassword}
                </button>
              </div>

              <Button type="submit" className="h-11 w-full rounded-lg bg-[#7367f0] text-white hover:bg-[#685dd8]" disabled={loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                <span className="ms-1">{t.login}</span>
              </Button>
            </form>
          </div>
        </section>
      </div>
    </div>
  );
}
