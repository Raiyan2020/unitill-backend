import { MessageSquareMore, TrendingDown, TrendingUp, User, Wallet, WifiOff, Zap } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '../components/ui/card';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type DashboardStats = {
  users_count: number;
  active_ads_count: number;
  inactive_ads_count: number;
  contact_messages_count: number;
  revenue: number;
  last_10_days?: Array<{
    date: string;
    label: string;
    ads_count: number;
    revenue: number;
  }>;
};

const bars = [38, 58, 42, 67, 50, 78, 56];

export function DashboardPage() {
  const { t, locale } = useI18n();
  const notify = useNotify();
  const didLoad = useRef(false);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<DashboardStats>({
    users_count: 0,
    active_ads_count: 0,
    inactive_ads_count: 0,
    contact_messages_count: 0,
    revenue: 0,
    last_10_days: [],
  });

  useEffect(() => {
    if (didLoad.current) return;
    didLoad.current = true;

    const load = async () => {
      setLoading(true);
      try {
        const res = await api.get('/admin/dashboard/stats');
        const data = ensureApiSuccess<DashboardStats>(res, 'Failed to load dashboard stats');
        setStats({
          users_count: Number(data?.users_count || 0),
          active_ads_count: Number(data?.active_ads_count || 0),
          inactive_ads_count: Number(data?.inactive_ads_count || 0),
          contact_messages_count: Number(data?.contact_messages_count || 0),
          revenue: Number(data?.revenue || 0),
          last_10_days: Array.isArray(data?.last_10_days) ? data.last_10_days : [],
        });
      } catch (error) {
        notify.errorFrom(error, 'Failed to load dashboard stats.');
      } finally {
        setLoading(false);
      }
    };

    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const cards = useMemo(
    () => [
      {
        title: locale === 'ar' ? 'عدد المستخدمين' : 'Users',
        subtitle: locale === 'ar' ? 'إجمالي المستخدمين' : 'Total platform users',
        value: stats.users_count,
        tone: 'positive' as const,
        trend: '+12.4%',
        icon: <User className="h-4 w-4" />,
      },
      {
        title: locale === 'ar' ? 'الإعلانات الفعالة' : 'Active Ads',
        subtitle: locale === 'ar' ? 'إعلانات منشورة' : 'Published advertisements',
        value: stats.active_ads_count,
        tone: 'positive' as const,
        trend: '+8.2%',
        icon: <Zap className="h-4 w-4" />,
      },
      {
        title: locale === 'ar' ? 'الإعلانات غير الفعالة' : 'Inactive Ads',
        subtitle: locale === 'ar' ? 'غير منشورة / مرفوضة' : 'Not published or rejected',
        value: stats.inactive_ads_count,
        tone: 'negative' as const,
        trend: '-4.1%',
        icon: <WifiOff className="h-4 w-4" />,
      },
      {
        title: locale === 'ar' ? 'رسائل اتصل بنا' : 'Contact Messages',
        subtitle: locale === 'ar' ? 'كل الرسائل الواردة' : 'All incoming messages',
        value: stats.contact_messages_count,
        tone: 'neutral' as const,
        trend: '+3.6%',
        icon: <MessageSquareMore className="h-4 w-4" />,
      },
      {
        title: locale === 'ar' ? 'الأرباح' : 'Revenue',
        subtitle: locale === 'ar' ? 'قيمة الإعلانات المنشورة' : 'Published ads value',
        value: stats.revenue,
        tone: 'revenue' as const,
        trend: '+24.5%',
        icon: <Wallet className="h-4 w-4" />,
        isMoney: true,
      },
    ],
    [locale, stats],
  );

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-xl font-semibold">{t.dashboard}</h2>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        {cards.map((card, index) => (
          <AnimatedMetricCard
            key={card.title}
            title={card.title}
            value={card.value}
            loading={loading}
            tone={card.tone}
            trend={card.trend}
            subtitle={card.subtitle}
            icon={card.icon}
            isMoney={card.isMoney}
            delayMs={index * 90}
          />
        ))}
      </div>

      <ChartCard data={stats.last_10_days || []} loading={loading} />
    </div>
  );
}

function ChartCard({
  data,
  loading,
}: {
  data: Array<{ date: string; label: string; ads_count: number; revenue: number }>;
  loading: boolean;
}) {
  const normalized = data.map((d) => ({
    ...d,
    ads_count: Number.isFinite(Number(d.ads_count)) ? Number(d.ads_count) : 0,
    revenue: Number.isFinite(Number(d.revenue)) ? Number(d.revenue) : 0,
  }));

  const maxAds = Math.max(1, ...normalized.map((d) => d.ads_count));
  const maxRevenue = Math.max(1, ...normalized.map((d) => d.revenue));

  const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));
  const chartW = 1000;
  const chartH = 260;
  const padX = 48;
  const padY = 20;
  const plotW = chartW - padX * 2;
  const plotH = chartH - padY * 2;
  const slot = normalized.length > 0 ? plotW / normalized.length : plotW;
  const barW = Math.min(26, Math.max(8, slot * 0.3));

  const xForIndex = (i: number) => padX + slot * i + slot / 2;
  const yForRevenue = (v: number) => padY + (1 - clamp(v / maxRevenue, 0, 1)) * plotH;
  const yForAds = (v: number) => padY + (1 - clamp(v / maxAds, 0, 1)) * plotH;

  const linePath = normalized
    .map((d, i) => {
      const x = xForIndex(i);
      const y = yForRevenue(d.revenue);
      return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    })
    .join(' ');

  return (
    <Card className="border-[#ececf3] bg-white shadow-[0_10px_24px_rgba(47,43,61,0.08)] dark:border-[#3f4462] dark:bg-[#2f3453] dark:shadow-[0_10px_24px_rgba(6,8,20,0.32)]">
      <CardContent className="p-4 md:p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-[#2f2b3d] dark:text-[#f4f5ff]">Ads & Revenue (Last 10 Days)</h3>
            <p className="text-sm text-[#8a8da8] dark:text-[#9ea3c6]">Daily ads count (bars) and revenue (line)</p>
          </div>
        </div>

        <div className="rounded-xl border border-[#ececf3] bg-[#fbfbfe] p-3 dark:border-[#454b6c] dark:bg-[#2b304d]">
          <div className="relative overflow-hidden rounded-lg">
            {loading ? (
              <div className="flex h-[260px] items-center justify-center text-sm text-[#8a8da8] dark:text-[#a7abd0]">...</div>
            ) : (
              <svg className="block h-[260px] w-full overflow-hidden" viewBox={`0 0 ${chartW} ${chartH}`} preserveAspectRatio="none">
                <defs>
                  <clipPath id="chartAreaClip">
                    <rect x={padX} y={padY} width={plotW} height={plotH} rx="8" ry="8" />
                  </clipPath>
                </defs>

                {[0, 25, 50, 75, 100].map((v) => {
                  const y = padY + (1 - v / 100) * plotH;
                  const adsTick = Math.round((v / 100) * maxAds);
                  return (
                    <g key={v}>
                      <line x1={padX} y1={y} x2={padX + plotW} y2={y} stroke="rgba(156,163,188,0.25)" strokeDasharray="4 6" />
                      <text
                        x={padX - 10}
                        y={y + 4}
                        textAnchor="end"
                        fontSize="11"
                        fill="rgba(143,149,188,0.95)"
                      >
                        {adsTick}
                      </text>
                    </g>
                  );
                })}

                <line x1={padX} y1={padY} x2={padX} y2={padY + plotH} stroke="rgba(156,163,188,0.35)" />
                <line x1={padX} y1={padY + plotH} x2={padX + plotW} y2={padY + plotH} stroke="rgba(156,163,188,0.35)" />

                <g clipPath="url(#chartAreaClip)">
                  {normalized.map((d, i) => {
                    const cx = xForIndex(i);
                    const y = yForAds(d.ads_count);
                    const h = Math.max(4, padY + plotH - y);
                    return <rect key={d.date} x={cx - barW / 2} y={padY + plotH - h} width={barW} height={h} rx="6" fill="#ff9f43" />;
                  })}

                  {normalized.length > 1 ? (
                    <path d={linePath} fill="none" stroke="#7367f0" strokeWidth="4" strokeLinejoin="round" strokeLinecap="round" />
                  ) : null}

                  {normalized.map((d, i) => {
                    const cx = xForIndex(i);
                    const cy = yForRevenue(d.revenue);
                    return <circle key={`${d.date}-dot`} cx={cx} cy={cy} r="6" fill="#ffffff" stroke="#7367f0" strokeWidth="3" />;
                  })}
                </g>
              </svg>
            )}
          </div>

          <div className="mt-3 grid grid-cols-10 gap-2 px-1">
            {normalized.map((d) => (
              <p key={`${d.date}-label`} className="truncate text-center text-[11px] text-[#8a8da8] dark:text-[#9ea3c6]">
                {d.label}
              </p>
            ))}
          </div>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-4 text-sm">
          <span className="inline-flex items-center gap-2 text-[#5d5971] dark:text-[#c3c7e4]">
            <span className="h-2.5 w-2.5 rounded-full bg-[#ff9f43]" />
            Ads
          </span>
          <span className="inline-flex items-center gap-2 text-[#5d5971] dark:text-[#c3c7e4]">
            <span className="h-2.5 w-2.5 rounded-full bg-[#7367f0]" />
            Revenue
          </span>
        </div>
      </CardContent>
    </Card>
  );
}

function AnimatedMetricCard({
  title,
  value,
  loading,
  tone,
  trend,
  subtitle,
  icon,
  isMoney,
  delayMs,
}: {
  title: string;
  value: number;
  loading: boolean;
  tone: 'positive' | 'negative' | 'neutral' | 'revenue';
  trend: string;
  subtitle: string;
  icon: ReactNode;
  isMoney?: boolean;
  delayMs?: number;
}) {
  const [displayValue, setDisplayValue] = useState(0);

  useEffect(() => {
    if (loading) return;
    const start = performance.now();
    const duration = 750;
    const from = displayValue;
    const to = Number.isFinite(value) ? value : 0;
    let frame = 0;

    const tick = (now: number) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const next = from + (to - from) * eased;
      setDisplayValue(next);
      if (progress < 1) {
        frame = requestAnimationFrame(tick);
      }
    };

    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, loading]);

  const toneStyles =
    tone === 'positive'
      ? {
          text: 'text-[#6d73ff]',
          trend: 'text-[#28c76f]',
          border: 'border-[#6d73ff]/30',
          bg: 'bg-[#6d73ff]/10',
        }
      : tone === 'negative'
        ? {
            text: 'text-[#ff6b6b]',
            trend: 'text-[#ea5455]',
            border: 'border-[#ff6b6b]/30',
            bg: 'bg-[#ff6b6b]/10',
          }
        : tone === 'revenue'
          ? {
              text: 'text-[#00cfe8]',
              trend: 'text-[#28c76f]',
              border: 'border-[#00cfe8]/30',
              bg: 'bg-[#00cfe8]/10',
            }
          : {
              text: 'text-[#9da3c7]',
              trend: 'text-[#a2a5be]',
              border: 'border-[#9da3c7]/30',
              bg: 'bg-[#9da3c7]/10',
            };

  const formatted = isMoney
    ? `$${Math.round(displayValue).toLocaleString()}`
    : Math.round(displayValue).toLocaleString();

  return (
    <Card
      className="border-[#ececf3] bg-white text-[#2f2b3d] shadow-[0_10px_24px_rgba(47,43,61,0.08)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_16px_30px_rgba(47,43,61,0.14)] dark:border-[#3f4462] dark:bg-[#2f3453] dark:text-[#d7d8ea] dark:shadow-[0_10px_24px_rgba(6,8,20,0.32)] dark:hover:shadow-[0_16px_30px_rgba(7,10,26,0.45)] animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: `${delayMs || 0}ms` }}
    >
      <CardContent className="p-4">
        <div className="flex items-start justify-between gap-2">
          <div className={`inline-flex h-8 w-8 items-center justify-center rounded-lg border ${toneStyles.border} ${toneStyles.bg} ${toneStyles.text}`}>
            {icon}
          </div>
          <div className="text-end">
            <p className="text-3xl font-semibold tracking-tight text-[#2f2b3d] dark:text-[#f4f5ff]">{loading ? '...' : formatted}</p>
          </div>
        </div>

        <p className="mt-2 text-sm text-[#5d5971] dark:text-[#c3c7e4]">{title}</p>
        <p className="mt-1 text-xs text-[#a5a3b1] dark:text-[#8f95bc]">{subtitle}</p>
        <div className="mt-2 flex items-center gap-1">
          {trend.startsWith('-') ? <TrendingDown className={`h-3.5 w-3.5 ${toneStyles.trend}`} /> : <TrendingUp className={`h-3.5 w-3.5 ${toneStyles.trend}`} />}
          <p className={`text-xs font-medium ${toneStyles.trend}`}>{trend}</p>
          <p className="text-xs text-[#a5a3b1] dark:text-[#8f95bc]">than last week</p>
        </div>

        {isMoney ? (
          <div className="mt-3 flex items-end gap-1.5">
            {bars.map((h, idx) => (
              <span
                key={idx}
                className={`w-1.5 rounded-full transition-all duration-500 ${idx === 4 ? 'bg-[#28c76f]' : 'bg-[#596082]'}`}
                style={{ height: `${Math.round(h * 0.45)}px` }}
              />
            ))}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
