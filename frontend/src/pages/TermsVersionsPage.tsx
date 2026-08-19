import { useEffect, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { hasPermission } from '../lib/auth';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type TermsVersion = {
  id: number;
  version: string;
  title_en: string;
  title_ar: string | null;
  content_en: string;
  content_ar: string | null;
  is_current: boolean;
  effective_at: string;
  acceptances_count: number;
};

const emptyForm = { version: '', title_en: '', title_ar: '', content_en: '', content_ar: '' };

export function TermsVersionsPage() {
  const { t, locale } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<TermsVersion[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const canPublish = hasPermission('legal_affairs.update');

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/terms-versions');
      setRows(ensureApiSuccess<TermsVersion[]>(res, t.actionFailed) || []);
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setLoading(false);
    }
  };

  // Initial fetch only; load is intentionally not a reactive subscription.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { load(); }, []);

  const publish = async () => {
    if (!form.version.trim() || !form.title_en.trim() || !form.content_en.trim()) {
      notify.error(t.requiredFields);
      return;
    }
    if (!window.confirm(t.publishTermsConfirmation)) return;

    setSaving(true);
    try {
      await api.post('/admin/terms-versions', {
        ...form,
        title_ar: form.title_ar.trim() || null,
        content_ar: form.content_ar.trim() || null,
      });
      notify.success(t.termsPublished);
      setForm(emptyForm);
      setShowForm(false);
      await load();
    } catch (error) {
      notify.errorFrom(error, t.actionFailed);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div><h2 className="text-2xl font-semibold">{t.termsVersions}</h2><p className="text-sm text-[#8a8da8]">{t.termsVersionsSubtitle}</p></div>
        {canPublish ? <Button onClick={() => setShowForm((value) => !value)}>{showForm ? t.cancel : t.publishNewTerms}</Button> : null}
      </div>

      {showForm ? <Card><CardHeader><CardTitle>{t.publishNewTerms}</CardTitle></CardHeader><CardContent className="space-y-3">
        <div className="grid gap-3 md:grid-cols-3">
          <Input placeholder={t.version} value={form.version} onChange={(e) => setForm((s) => ({ ...s, version: e.target.value }))} />
          <Input placeholder={`${t.title} (EN)`} value={form.title_en} onChange={(e) => setForm((s) => ({ ...s, title_en: e.target.value }))} />
          <Input placeholder={`${t.title} (AR)`} dir="rtl" value={form.title_ar} onChange={(e) => setForm((s) => ({ ...s, title_ar: e.target.value }))} />
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          <textarea className="min-h-52 rounded-xl border bg-white p-3 text-sm dark:bg-[#2f3349]" placeholder={`${t.termsContent} (EN)`} value={form.content_en} onChange={(e) => setForm((s) => ({ ...s, content_en: e.target.value }))} />
          <textarea className="min-h-52 rounded-xl border bg-white p-3 text-sm dark:bg-[#2f3349]" dir="rtl" placeholder={`${t.termsContent} (AR)`} value={form.content_ar} onChange={(e) => setForm((s) => ({ ...s, content_ar: e.target.value }))} />
        </div>
        <p className="text-xs text-amber-600">{t.newTermsWarning}</p>
        <div className="flex justify-end"><Button disabled={saving} onClick={publish}>{t.publish}</Button></div>
      </CardContent></Card> : null}

      <Card><CardContent className="p-0"><div className="overflow-x-auto"><table className="w-full text-sm">
        <thead className="bg-[#f8f7fb] dark:bg-[#383d56]"><tr><th className="px-4 py-3 text-start">{t.version}</th><th className="px-4 py-3 text-start">{t.title}</th><th className="px-4 py-3 text-start">{t.effectiveAt}</th><th className="px-4 py-3 text-start">{t.acceptances}</th><th className="px-4 py-3 text-start">{t.status}</th></tr></thead>
        <tbody>{loading ? <tr><td colSpan={5} className="p-8 text-center">{t.loading}</td></tr> : rows.map((row) => <tr key={row.id} className="border-t dark:border-[#44485f]"><td className="px-4 py-3 font-semibold">{row.version}</td><td className="px-4 py-3">{locale === 'ar' ? row.title_ar || row.title_en : row.title_en}</td><td className="px-4 py-3">{new Date(row.effective_at).toLocaleString(locale)}</td><td className="px-4 py-3">{row.acceptances_count}</td><td className="px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs ${row.is_current ? 'bg-green-500/15 text-green-700' : 'bg-slate-500/15'}`}>{row.is_current ? t.current : t.previousVersion}</span></td></tr>)}</tbody>
      </table></div></CardContent></Card>
    </div>
  );
}
