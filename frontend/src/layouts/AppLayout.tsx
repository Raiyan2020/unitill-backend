import { Building2, FileText, FolderTree, Globe, KeyRound, Languages, LayoutDashboard, LogOut, Mail, Menu, MessageSquare, Moon, ShieldCheck, Sun, User, UserCog, Users, Wallet, X } from 'lucide-react';
import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { api } from '../lib/api';
import { clearAuthToken } from '../lib/auth';
import { cn } from '../lib/cn';
import { useI18n } from '../providers/i18n-provider';

const navItems = [
  { to: '/', labelKey: 'dashboard', icon: LayoutDashboard },
  { to: '/users', labelKey: 'users', icon: Users },
  { to: '/admins', labelKey: 'admins', icon: UserCog },
  { to: '/roles', labelKey: 'roles', icon: ShieldCheck },
  { to: '/permissions', labelKey: 'permissions', icon: KeyRound },
  { to: '/countries', labelKey: 'countries', icon: Building2 },
  { to: '/categories', labelKey: 'categories', icon: FolderTree },
  { to: '/languages', labelKey: 'languages', icon: Languages },
  { to: '/legal-affairs', labelKey: 'legalAffairs', icon: FileText },
  { to: '/contact-reasons', labelKey: 'contactReasons', icon: MessageSquare },
  { to: '/contact-us', labelKey: 'contactUs', icon: Mail },
  { to: '/payment-methods', labelKey: 'paymentMethods', icon: Wallet },
] as const;

export function AppLayout() {
  const navigate = useNavigate();
  const { t, locale, setLocale, theme, setTheme } = useI18n();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="min-h-screen bg-[#f5f5f9] text-[#2f2b3d] dark:bg-[#25293c] dark:text-[#d7d8ea]">
      {sidebarOpen ? (
        <button
          type="button"
          aria-label="Close sidebar"
          onClick={() => setSidebarOpen(false)}
          className="fixed inset-0 z-20 bg-black/35 md:hidden"
        />
      ) : null}

      <aside
        className={cn(
          'fixed z-30 w-[264px] border border-[#e6e6ef] bg-white shadow-[0_10px_24px_rgba(47,43,61,0.12)] transition-transform dark:border-[#44485f] dark:bg-[#2f3349] dark:shadow-[0_12px_28px_rgba(0,0,0,0.35)]',
          'inset-y-0 start-0 rounded-none',
          sidebarOpen ? 'translate-x-0 rtl:translate-x-0' : '-translate-x-full rtl:translate-x-full',
          'md:inset-y-3 md:start-3 md:rounded-2xl md:translate-x-0 md:rtl:translate-x-0',
        )}
      >
        <div className="mx-3 mt-3 flex h-14 items-center rounded-xl bg-[#7367f0] px-4 text-white shadow-[0_10px_20px_rgba(115,103,240,0.35)] transition-all duration-300">
          <h1 className="text-base font-semibold tracking-wide text-white">{t.appName}</h1>
          <div className="ms-2 flex h-7 w-7 items-center justify-center rounded-lg bg-white text-[#7367f0] shadow-sm">U</div>
          <Button variant="ghost" size="icon" className="ms-auto text-white hover:bg-white/20 md:hidden" onClick={() => setSidebarOpen(false)}>
            <X size={16} />
          </Button>
        </div>

        <div className="px-3 pb-4">
          <p className="px-2 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-[#a5a7b8]">Dashboards</p>
          <nav className="space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const label = t[item.labelKey];

              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  end={item.to === '/'}
                  style={({ isActive }) =>
                    isActive
                      ? {
                          backgroundColor: '#7367f0',
                          color: '#ffffff',
                          boxShadow: '0 8px 18px rgba(115,103,240,0.35)',
                        }
                      : undefined
                  }
                  className={({ isActive }) =>
                    cn(
                      'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                      isActive
                        ? '!bg-[#7367f0] !text-white shadow-[0_8px_18px_rgba(115,103,240,0.35)]'
                        : 'text-[#6f6b7d] hover:bg-[#f4f5fa] dark:text-[#b6b8cc] dark:hover:bg-[#3a3f57]',
                    )
                  }
                >
                  <Icon size={17} className="shrink-0" />
                  {label}
                </NavLink>
              );
            })}
          </nav>
        </div>
      </aside>

      <div className="ms-0 flex min-h-screen flex-col md:ms-[288px]">
        <header className="sticky top-0 z-10 m-2 flex h-auto min-h-16 items-center justify-between rounded-2xl border border-[#e6e6ef] bg-white px-3 py-2 shadow-[0_8px_22px_rgba(47,43,61,0.08)] backdrop-blur dark:border-[#44485f] dark:bg-[#2f3349]/95 dark:shadow-[0_10px_24px_rgba(0,0,0,0.28)] md:m-3 md:h-16 md:px-6 md:py-0">
          <input
            placeholder="Search (CTRL + K)"
            className="h-10 w-full max-w-[220px] rounded-lg border border-[#e0e1ec] bg-[#f8f8fc] px-3 text-sm outline-none focus:ring-2 focus:ring-[#7367f0] dark:border-[#484d66] dark:bg-[#383d56] md:max-w-[320px]"
          />

          <div className="flex items-center gap-1 md:gap-2">
            <Button variant="ghost" size="icon" onClick={() => setSidebarOpen(true)} className="md:hidden" title="Menu">
              <Menu size={16} />
            </Button>
            <Button variant="ghost" size="icon" onClick={() => setLocale(locale === 'en' ? 'ar' : 'en')} title={t.language}>
              <Globe size={16} />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
              title={t.theme}
            >
              {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
            </Button>
            <Button variant="ghost" size="icon" title={t.profile} onClick={() => navigate('/profile')}>
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[#7367f0]/15 text-[#7367f0] dark:bg-[#7367f0]/25">
                <User size={15} />
              </span>
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="hidden sm:inline-flex"
              onClick={async () => {
                try {
                  await api.post('/admin/logout');
                } catch {
                  // ignore logout API errors
                } finally {
                  clearAuthToken();
                  navigate('/login', { replace: true });
                }
              }}
            >
              <LogOut size={16} className="me-1" />
              {t.logout}
            </Button>
          </div>
        </header>

        <main className="flex-1 p-3 pt-1 md:p-6 md:pt-1">
          <Outlet />
        </main>
        <footer className="mt-auto border-t border-[#e6e6ef] px-3 py-4 text-center text-sm text-[#8a8da8] dark:border-[#44485f] dark:text-[#a2a5be] md:px-6">
          RaiyanSoft © {new Date().getFullYear()}
        </footer>
      </div>
    </div>
  );
}
