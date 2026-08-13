import { BadgeCheck, Bell, Building2, FileText, Flag, MessageSquareWarning, TicketPercent, FolderTree, GraduationCap, Globe, Languages, LayoutDashboard, LogOut, Mail, Megaphone, Menu, MessageSquare, Moon, RotateCcw, Settings, ShieldCheck, Sun, User, UserCog, Users, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { api } from '../lib/api';
import { clearAuthToken, getAdminAuthInfo, setAdminAuthInfo } from '../lib/auth';
import { cn } from '../lib/cn';
import { useI18n } from '../providers/i18n-provider';

const navItems = [
  { to: '/', labelKey: 'dashboard', icon: LayoutDashboard, permission: 'dashboard.view' },
  { to: '/users', labelKey: 'users', icon: Users, permission: 'users.view' },
  { to: '/ads', labelKey: 'ads', icon: Megaphone, permission: 'categories.view' },
  { to: '/refund-requests', labelKey: 'refundRequests', icon: RotateCcw, permission: 'categories.view' },
  { to: '/ad-reports', labelKey: 'adReports', icon: Flag, permission: 'ad_reports.view' },
  { to: '/chat-reports', labelKey: 'chatReports', icon: MessageSquareWarning, permission: 'chat_reports.view' },
  { to: '/user-verifications', labelKey: 'userVerifications', icon: BadgeCheck, permission: 'users.view' },
  { to: '/admins', labelKey: 'admins', icon: UserCog, permission: 'admins.view' },
  { to: '/roles', labelKey: 'roles', icon: ShieldCheck, permission: 'roles.view' },
  { to: '/countries', labelKey: 'countries', icon: Building2, permission: 'countries.view' },
  { to: '/universities', labelKey: 'universities', icon: GraduationCap, permission: 'universities.view' },
  { to: '/categories', labelKey: 'categories', icon: FolderTree, permission: 'categories.view' },
  { to: '/languages', labelKey: 'languages', icon: Languages, permission: 'languages.view' },
  { to: '/legal-affairs', labelKey: 'legalAffairs', icon: FileText, permission: 'legal_affairs.view' },
  { to: '/contact-reasons', labelKey: 'contactReasons', icon: MessageSquare, permission: 'contact_reasons.view' },
  { to: '/contact-us', labelKey: 'contactUs', icon: Mail, permission: 'contact_us.view' },
  { to: '/push-notifications', labelKey: 'pushNotifications', icon: Bell, permission: 'dashboard.view' },
  { to: '/coupons', labelKey: 'coupons', icon: TicketPercent, permission: 'coupons.view' },
  // Payment methods is hidden from the sidebar: the listing fee goes through
  // Stripe, which never consults this table. The route and page remain reachable
  // by URL so nothing is lost if it is needed again.
  // { to: '/payment-methods', labelKey: 'paymentMethods', icon: Wallet, permission: 'payment_methods.view' },
  { to: '/settings', labelKey: 'settings', icon: Settings, permission: 'dashboard.view' },
] as const;

export function AppLayout() {
  const navigate = useNavigate();
  const { t, locale, setLocale, theme, setTheme } = useI18n();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [pendingVerificationCount, setPendingVerificationCount] = useState(0);
  const [logoError, setLogoError] = useState(false);
  const [adminInfo, setAdminInfo] = useState(() => getAdminAuthInfo());
  const [adminPermissions, setAdminPermissions] = useState<string[]>(
    () => getAdminAuthInfo()?.permissions ?? [],
  );
  const projectLogoSrc = '/project-logo.png';
  const visibleNavItems = useMemo(
    () => navItems.filter((item) => adminPermissions.includes(item.permission)),
    [adminPermissions],
  );

  const loadPendingCount = useCallback(async () => {
    try {
      const res = await api.get('/admin/trusted-seller-applications/pending-count');
      const count = Number(res?.data?.data?.count || 0);
      setPendingVerificationCount(Number.isNaN(count) ? 0 : count);
    } catch {
      setPendingVerificationCount(0);
    }
  }, []);

  useEffect(() => {
    loadPendingCount();

    const refreshPermissions = async () => {
      try {
        const res = await api.get('/admin/profile');
        const admin = res.data?.data;
        const current = getAdminAuthInfo();
        if (admin && current) {
          const permissions = Array.isArray(admin.permissions) ? admin.permissions : current.permissions;
          const updatedAdmin = {
            ...current,
            name: admin.name ?? current.name,
            email: admin.email ?? current.email,
            roles: admin.roles ?? current.roles,
            permissions,
          };
          setAdminAuthInfo(updatedAdmin);
          setAdminInfo(updatedAdmin);
          setAdminPermissions(permissions);
        }
      } catch {
        // ignore — sidebar still works with cached permissions
      }
    };

    refreshPermissions();

    const onVerificationChanged = () => {
      loadPendingCount();
    };

    window.addEventListener('trusted-verifications-changed', onVerificationChanged);
    return () => window.removeEventListener('trusted-verifications-changed', onVerificationChanged);
  }, [loadPendingCount]);

  return (
    <div className="min-h-screen bg-[#f5f5f9] text-[#2f2b3d] dark:bg-[#25293c] dark:text-[#d7d8ea]">
      {sidebarOpen ? (
        <button
          type="button"
          aria-label={t.closeSidebar}
          onClick={() => setSidebarOpen(false)}
          className="fixed inset-0 z-20 bg-black/35 md:hidden"
        />
      ) : null}

      <aside
        className={cn(
          'fixed z-30 w-[264px] overflow-y-auto border border-[#e6e6ef] bg-white shadow-[0_10px_24px_rgba(47,43,61,0.12)] transition-transform dark:border-[#44485f] dark:bg-[#2f3349] dark:shadow-[0_12px_28px_rgba(0,0,0,0.35)] [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden',
          'inset-y-0 start-0 rounded-none',
          sidebarOpen ? 'translate-x-0 rtl:translate-x-0' : '-translate-x-full rtl:translate-x-full',
          'md:inset-y-3 md:start-3 md:rounded-2xl md:translate-x-0 md:rtl:translate-x-0',
        )}
        style={{ scrollBehavior: 'smooth' }}
      >
        <div className="mx-3 mt-3 flex h-14 items-center rounded-xl bg-[#7367f0] px-4 text-white shadow-[0_10px_20px_rgba(115,103,240,0.35)] transition-all duration-300">
          <h1 className="text-base font-semibold tracking-wide text-white">{t.appName}</h1>
          <div className="ms-2 flex h-7 w-7 items-center justify-center rounded-lg bg-white text-[#7367f0] shadow-sm">U</div>
          <Button variant="ghost" size="icon" className="ms-auto text-white hover:bg-white/20 md:hidden" onClick={() => setSidebarOpen(false)}>
            <X size={16} />
          </Button>
        </div>

        <div className="px-3 pb-4">
          <div className="mb-3 mt-3 flex items-center gap-3 rounded-xl border border-[#ececf3] bg-[#fafafe] p-2.5 dark:border-[#44485f] dark:bg-[#383d56]">
            <div className="relative h-11 w-11 shrink-0">
              {!logoError ? (
                <img
                  src={projectLogoSrc}
                  alt="Project logo"
                  className="h-11 w-11 rounded-full object-cover ring-2 ring-[#7367f0]/25"
                  onError={() => setLogoError(true)}
                />
              ) : (
                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-[#7367f0]/15 text-sm font-bold text-[#7367f0] ring-2 ring-[#7367f0]/25">
                  U
                </div>
              )}
              <div className="absolute -end-0.5 -bottom-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-[#28c76f] dark:border-[#383d56]" />
            </div>
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{adminInfo?.name || t.appName}</p>
              <p className="text-xs text-[#8a8da8] dark:text-[#a2a5be]">{t.connected}</p>
            </div>
          </div>

          <p className="px-2 pb-2 pt-3 text-[11px] font-semibold uppercase tracking-wider text-[#a5a7b8]">{t.dashboards}</p>
          <nav className="space-y-1">
            {visibleNavItems.map((item) => {
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
                  {item.to === '/user-verifications' && pendingVerificationCount > 0 ? (
                    <span className="ms-auto inline-flex min-w-[20px] items-center justify-center rounded-full bg-[#ea5455] px-1.5 py-0.5 text-[11px] font-semibold text-white">
                      {pendingVerificationCount > 99 ? '99+' : pendingVerificationCount}
                    </span>
                  ) : null}
                </NavLink>
              );
            })}
          </nav>
        </div>
      </aside>

      <div className="ms-0 flex min-h-screen flex-col md:ms-[288px]">
        <header className="sticky top-0 z-10 m-2 flex h-auto min-h-16 items-center justify-end rounded-2xl border border-[#e6e6ef] bg-white px-3 py-2 shadow-[0_8px_22px_rgba(47,43,61,0.08)] backdrop-blur dark:border-[#44485f] dark:bg-[#2f3349]/95 dark:shadow-[0_10px_24px_rgba(0,0,0,0.28)] md:m-3 md:h-16 md:px-6 md:py-0">
          <div className="flex items-center gap-1 md:gap-2">
            <Button variant="ghost" size="icon" onClick={() => setSidebarOpen(true)} className="md:hidden" title={t.menu}>
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
