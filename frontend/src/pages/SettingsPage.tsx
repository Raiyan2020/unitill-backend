import { Edit3 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type SettingRow = {
  id: number;
  key_id: string;
  title_en: string | null;
  title_ar: string | null;
  value: string | null;
  set_group: string | null;
  is_object: boolean;
};

export function SettingsPage() {
  const { t, locale } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<SettingRow[]>([]);
  const [values, setValues] = useState<Record<string, string>>({});
  const [editing, setEditing] = useState<Record<string, boolean>>({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/settings');
      const data = ensureApiSuccess<SettingRow[]>(res, 'Failed to load settings');
      setRows(data || []);
      const mapped: Record<string, string> = {};
      for (const row of data || []) {
        mapped[row.key_id] = row.value ?? '';
      }
      setValues(mapped);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load settings.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fields = useMemo(
    () =>
      [...rows]
        .sort((a, b) => {
          const order = ['post_price', 'free_ads_per_user'];
          const aIndex = order.indexOf(a.key_id);
          const bIndex = order.indexOf(b.key_id);
          return (aIndex === -1 ? order.length : aIndex) - (bIndex === -1 ? order.length : bIndex);
        })
        .map((row) => ({
        key: row.key_id,
        label: locale === 'ar' ? row.title_ar || row.title_en || row.key_id : row.title_en || row.title_ar || row.key_id,
        // Price was historically stored as an "object" setting, but both
        // listing controls must remain compact, editable number inputs.
        isObject: ['post_price', 'free_ads_per_user'].includes(row.key_id) ? false : row.is_object,
        isNumber: ['post_price', 'free_ads_per_user'].includes(row.key_id),
      })),
    [rows, locale],
  );

  const save = async () => {
    setSaving(true);
    try {
      const changedItems = Object.entries(values)
        .filter(([key]) => editing[key])
        .map(([key, value]) => ({ key_id: key, value }));

      if (changedItems.length === 0) {
        notify.success('No changes to save.');
        return;
      }

      await api.put('/admin/settings', { items: changedItems });
      setEditing({});
      notify.success('Settings updated successfully.');
    } catch (error) {
      notify.errorFrom(error, 'Failed to save settings.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4 animate-in fade-in duration-300">
      <div>
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.settings}</h2>
        <p className="text-sm text-[#8a8da8] dark:text-[#a2a5be]">Manage platform settings and contact info.</p>
      </div>

      <Card className="shadow-[0_10px_24px_rgba(47,43,61,0.08)] dark:shadow-[0_10px_24px_rgba(0,0,0,0.25)]">
        <CardHeader>
          <CardTitle>{t.settings}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="py-10 text-center text-sm text-[#8a8da8] dark:text-[#a2a5be]">Loading settings...</div>
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
            {fields.map((field) => {
              const isEditing = Boolean(editing[field.key]);
              return (
                <div key={field.key} className="space-y-1.5">
                  <label className="text-xs font-medium text-[#6f6b7d] dark:text-[#b6b8cc]">{field.label}</label>
                  <div className="flex items-center gap-2">
                    {field.isObject ? (
                      <textarea
                        value={values[field.key] ?? ''}
                        disabled={!isEditing}
                        onChange={(e) => setValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        className={`min-h-[110px] w-full rounded-xl border border-[#dbdbe8] bg-white px-3 py-2 text-sm text-[#2f2b3d] outline-none focus:ring-2 focus:ring-[#7367f0]/40 dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea] ${isEditing ? '' : 'opacity-80'}`}
                      />
                    ) : (
                      <Input
                        type={field.isNumber ? 'number' : 'text'}
                        min={field.isNumber ? '0' : undefined}
                        step={field.key === 'post_price' ? '0.01' : field.isNumber ? '1' : undefined}
                        value={values[field.key] ?? ''}
                        disabled={!isEditing}
                        onChange={(e) => setValues((prev) => ({ ...prev, [field.key]: e.target.value }))}
                        className={isEditing ? '' : 'opacity-80'}
                      />
                    )}
                    <Button
                      type="button"
                      size="icon"
                      variant={isEditing ? 'default' : 'secondary'}
                      onClick={() => setEditing((prev) => ({ ...prev, [field.key]: !prev[field.key] }))}
                      title={t.edit}
                    >
                      <Edit3 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              );
            })}
            </div>
          )}

          <div className="mt-5 flex items-center gap-2">
            <Button onClick={save} disabled={saving}>
              {t.save}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                const mapped: Record<string, string> = {};
                for (const row of rows) {
                  mapped[row.key_id] = row.value ?? '';
                }
                setValues(mapped);
                setEditing({});
              }}
            >
              {t.cancel}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
