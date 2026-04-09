import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type AdStatus = 'draft' | 'pending' | 'published' | 'rejected' | 'sold' | 'expired';

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
  images: { id: number; url: string; sort_order: number }[];
  created_at: string | null;
  updated_at: string | null;
};

export function AdDetailsPage() {
  const { t } = useI18n();
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
        const payload = ensureApiSuccess<AdDetails>(res, 'Failed to load ad details');
        setAd(payload || null);
      } catch (error) {
        notify.errorFrom(error, 'Failed to load ad details.');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id]);

  if (loading) {
    return (
      <Card>
        <CardContent className="py-10 text-center text-sm text-[#8a8da8]">Loading...</CardContent>
      </Card>
    );
  }

  if (!ad) {
    return (
      <Card>
        <CardContent className="py-10 text-center text-sm text-[#8a8da8]">Ad not found.</CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">Ad Details</h2>
        <Link to="/ads">
          <Button variant="secondary">{t.cancel}</Button>
        </Link>
      </div>

      <Card className="overflow-hidden">
        <CardHeader className="bg-gradient-to-r from-[#7367f0] to-[#8d84ff] text-white">
          <CardTitle className="text-xl">{ad.title || '-'}</CardTitle>
          <p className="text-sm text-white/90">#{ad.public_id || ad.id}</p>
        </CardHeader>
        <CardContent className="space-y-5 p-5">
          {ad.cover_image_url ? (
            <img src={ad.cover_image_url} alt="" className="h-64 w-full rounded-xl object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" />
          ) : null}

          <div className="grid gap-3 md:grid-cols-3">
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">Status</p>
              <p className="mt-1 text-sm font-semibold capitalize">{ad.status}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">Price</p>
              <p className="mt-1 text-sm font-semibold">{ad.price ? `${ad.price} ${ad.currency || ''}`.trim() : '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">User</p>
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
              <p className="text-xs text-[#8a8da8]">Subtitle</p>
              <p className="mt-1 text-sm">{ad.subtitle || '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">Slug</p>
              <p className="mt-1 text-sm break-all">{ad.slug || '-'}</p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">Country / City</p>
              <p className="mt-1 text-sm">
                {(ad.country?.country_code || '-')}/{ad.city?.code || '-'}
              </p>
            </div>
            <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
              <p className="text-xs text-[#8a8da8]">Created At</p>
              <p className="mt-1 text-sm">{ad.created_at || '-'}</p>
            </div>
          </div>

          <div className="rounded-xl border border-[#ececf3] p-3 dark:border-[#44485f]">
            <p className="text-xs text-[#8a8da8]">Description</p>
            <p className="mt-1 whitespace-pre-wrap text-sm">{ad.description || '-'}</p>
          </div>

          {ad.images?.length ? (
            <div>
              <p className="mb-2 text-sm font-semibold">Gallery</p>
              <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {ad.images.map((img) => (
                  <img key={img.id} src={img.url} alt="" className="h-28 w-full rounded-lg object-cover ring-1 ring-[#ececf3] dark:ring-[#44485f]" />
                ))}
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}

