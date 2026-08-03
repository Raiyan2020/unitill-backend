import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type AdStatus = 'draft' | 'pending' | 'published' | 'rejected' | 'sold' | 'expired';

type AdAttribute = {
  slug: string;
  label: string;
  input_type: 'string' | 'number' | 'boolean' | 'select' | 'date' | 'multiselect';
  value: string | null;
  /** Localized display text; the API falls back to value when nothing needs translating. */
  value_label?: string | null;
};

type AdDetails = {
  id: number;
  public_id: string;
  title: string;
  subtitle: string | null;
  description: string | null;
  status: AdStatus;
  price: string | null;
  currency: string | null;
  is_negotiable: boolean;
  is_verified: boolean;
  slug: string | null;
  cover_image_url: string | null;
  published_at: string | null;
  user: { id: number | null; name: string; email: string | null; phone: string | null } | null;
  country: { id: number; country_code: string } | null;
  city: { id: number; code: string | null } | null;
  main_category_id: number | null;
  sub_category_id: number | null;
  main_category_name: string | null;
  sub_category_name: string | null;
  attributes: AdAttribute[];
  images: { id: number; url: string; sort_order: number }[];
  created_at: string | null;
  updated_at: string | null;
};

/**
 * Attribute values are always stored as strings, so each input_type needs its own
 * read-back. Unknown types fall through to the raw value rather than rendering blank,
 * so a newly added input_type still shows something useful here.
 */
function formatAttributeValue(attribute: AdAttribute, yes: string, no: string, locale: 'en' | 'ar') {
  // Prefer the localized label; value stays the raw English option key.
  const raw = (attribute.value_label ?? attribute.value ?? '').trim();
  if (!raw) return '-';

  switch (attribute.input_type) {
    case 'multiselect':
      return raw
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean)
        .join(', ');
    case 'boolean':
      return ['1', 'true', 'yes'].includes(raw.toLowerCase()) ? yes : no;
    case 'date': {
      const parsed = new Date(raw);
      return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB');
    }
    default:
      return raw;
  }
}

export function AdDetailsPage() {
  const { t, locale } = useI18n();
  const notify = useNotify();
  const { id } = useParams();
  const [loading, setLoading] = useState(false);
  const [ad, setAd] = useState<AdDetails | null>(null);

  useEffect(() => {
    const load = async () => {
      if (!id) return;
      setLoading(true);
      try {
        const res = await api.get(`/admin/ads/${id}`);
        const payload = ensureApiSuccess<AdDetails>(res, t.actionFailed);
        setAd(payload || null);
      } catch (error) {
        notify.errorFrom(error, t.actionFailed);
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id]);

  if (loading) {
    return (
      <Card>
        <CardContent className="py-10 text-center text-sm text-[#8a8da8]">{t.loading}</CardContent>
      </Card>
    );
  }

  if (!ad) {
    return (
      <Card>
        <CardContent className="py-10 text-center text-sm text-[#8a8da8]">{t.adNotFound}</CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.adDetails}</h2>
        <Link to="/ads">
          <Button variant="secondary">{t.back}</Button>
        </Link>
      </div>

      <Card className="overflow-hidden">
        <CardHeader className="bg-gradient-to-r from-[#7367f0] to-[#8d84ff] text-white">
          <CardTitle className="text-xl">{ad.title || '-'}</CardTitle>
          <p className="text-sm text-white/90">#{ad.public_id || ad.id}</p>
        </CardHeader>
        <CardContent className="space-y-5 p-5">
          {ad.cover_image_url ? (
            <a href={ad.cover_image_url} target="_blank" rel="noreferrer" className="block">
              <img src={ad.cover_image_url} alt={ad.title} className="h-64 w-full cursor-zoom-in rounded-xl object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" />
            </a>
          ) : null}

          <div className="grid gap-3 md:grid-cols-3">
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.status}</p>
              <p className="mt-1 text-sm font-semibold">{adStatusLabel(ad.status, t)}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.price}</p>
              <p className="mt-1 text-sm font-semibold">{ad.price ? `${ad.price} ${ad.currency || ''}`.trim() : '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.user}</p>
              {ad.user?.id ? (
                <Link to={`/ads/user/${ad.user.id}`} className="mt-1 inline-block text-sm font-semibold text-[#7367f0] hover:underline">
                  {ad.user.name}
                </Link>
              ) : (
                <p className="mt-1 text-sm font-semibold">-</p>
              )}
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.subtitle}</p>
              <p className="mt-1 text-sm">{ad.subtitle || '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.slug}</p>
              <p className="mt-1 text-sm break-all">{ad.slug || '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.countryAndCity}</p>
              <p className="mt-1 text-sm">
                {(ad.country?.country_code || '-')}/{ad.city?.code || '-'}
              </p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.createdAt}</p>
              <p className="mt-1 text-sm">{formatDateTime(ad.created_at, locale)}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">{t.category}</p>
              <p className="mt-1 text-sm">
                {ad.main_category_name || '-'}
                {ad.sub_category_name ? ` / ${ad.sub_category_name}` : ''}
              </p>
            </div>
          </div>

          <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
            <p className="text-xs text-[#8a8da8]">{t.description}</p>
            <p className="mt-1 whitespace-pre-wrap text-sm">{ad.description || '-'}</p>
          </div>

          <div>
            <p className="mb-2 text-sm font-semibold">{t.specifications}</p>
            {ad.attributes?.length ? (
              <div className="grid gap-3 md:grid-cols-3">
                {ad.attributes.map((attribute) => (
                  <div
                    key={attribute.slug}
                    className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]"
                  >
                    <p className="text-xs text-[#8a8da8]">{attribute.label}</p>
                    <p className="mt-1 text-sm font-semibold">{formatAttributeValue(attribute, t.yes, t.no, locale)}</p>
                  </div>
                ))}
              </div>
            ) : (
              <div className="rounded-xl border border-dashed border-[#ececf3] p-3 text-sm text-[#8a8da8] dark:border-[#44485f]">
                {t.noSpecifications}
              </div>
            )}
          </div>

          {ad.images?.length ? (
            <div>
              <p className="mb-2 text-sm font-semibold">{t.gallery}</p>
              <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {ad.images.map((img) => (
                  <a key={img.id} href={img.url} target="_blank" rel="noreferrer">
                    <img src={img.url} alt={ad.title} className="h-28 w-full cursor-zoom-in rounded-lg object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" />
                  </a>
                ))}
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}

function adStatusLabel(status: AdStatus, t: ReturnType<typeof useI18n>['t']): string {
  return {
    draft: t.draft,
    pending: t.pending,
    published: t.published,
    rejected: t.rejected,
    sold: t.sold,
    expired: t.expired,
  }[status];
}

function formatDateTime(value: string | null, locale: 'en' | 'ar'): string {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}
