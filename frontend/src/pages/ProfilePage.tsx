import { Lock, Shield, Upload, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { api } from '../lib/api';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type ProfilePayload = {
  id: number;
  name: string;
  email: string;
  image_url: string | null;
  roles: string[];
  permissions: string[];
};

type TabType = 'account' | 'security';

export function ProfilePage() {
  const { t } = useI18n();
  const notify = useNotify();
  const photoInputRef = useRef<HTMLInputElement>(null);
  const [tab, setTab] = useState<TabType>('account');
  const [loading, setLoading] = useState(false);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [savingPhoto, setSavingPhoto] = useState(false);
  const [profile, setProfile] = useState<ProfilePayload | null>(null);

  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
  });

  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const fetchProfile = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/profile');
      const payload: ProfilePayload = res.data?.data;
      setProfile(payload);

      setProfileForm({ name: payload?.name || '', email: payload?.email || '' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProfile();
  }, []);

  const updateProfile = async () => {
    setSavingProfile(true);
    try {
      await api.put('/admin/profile', profileForm);
      await fetchProfile();
      notify.success(t.profileUpdated);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSavingProfile(false);
    }
  };

  const updatePassword = async () => {
    setSavingPassword(true);
    try {
      await api.put('/admin/profile/password', passwordForm);
      setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
      notify.success(t.profileUpdated);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSavingPassword(false);
    }
  };

  const uploadPhoto = async (file: File) => {
    setSavingPhoto(true);
    try {
      const payload = new FormData();
      payload.append('image', file);
      await api.post('/admin/profile/photo', payload);
      await fetchProfile();
      notify.success(t.photoUpdated);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSavingPhoto(false);
      if (photoInputRef.current) photoInputRef.current.value = '';
    }
  };

  const resetPhoto = async () => {
    setSavingPhoto(true);
    try {
      await api.delete('/admin/profile/photo');
      await fetchProfile();
      notify.success(t.photoRemoved);
    } catch (error) {
      notify.errorFrom(error, t.updateFailed);
    } finally {
      setSavingPhoto(false);
    }
  };

  return (
    <div className="space-y-4 animate-in fade-in duration-300">
      <div className="flex flex-wrap gap-2 rounded-xl border border-[#e6e6ef] bg-white p-2 shadow-[0_8px_20px_rgba(47,43,61,0.06)] dark:border-[#44485f] dark:bg-[#2f3349]">
        <button
          className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all ${
            tab === 'account'
              ? 'bg-[#7367f0] text-white shadow-[0_8px_18px_rgba(115,103,240,0.35)]'
              : 'text-[#6f6b7d] hover:bg-[#f4f5fa] dark:text-[#b6b8cc] dark:hover:bg-[#3a3f57]'
          }`}
          onClick={() => setTab('account')}
        >
          <User className="h-4 w-4" />
          {t.account}
        </button>
        <button
          className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all ${
            tab === 'security'
              ? 'bg-[#7367f0] text-white shadow-[0_8px_18px_rgba(115,103,240,0.35)]'
              : 'text-[#6f6b7d] hover:bg-[#f4f5fa] dark:text-[#b6b8cc] dark:hover:bg-[#3a3f57]'
          }`}
          onClick={() => setTab('security')}
        >
          <Lock className="h-4 w-4" />
          {t.security}
        </button>
      </div>

      {tab === 'account' ? (
        <Card className="shadow-[0_10px_24px_rgba(47,43,61,0.08)] dark:shadow-[0_10px_24px_rgba(0,0,0,0.25)]">
          <CardContent className="space-y-6 p-5">
            <div className="flex flex-wrap items-center gap-4">
              <div className="flex h-20 w-20 overflow-hidden items-center justify-center rounded-xl bg-[#7367f0]/15 text-[#7367f0]">
                {profile?.image_url ? (
                  <img src={profile.image_url} alt={profile.name} className="h-full w-full object-cover" />
                ) : (
                  <User className="h-10 w-10" />
                )}
              </div>
              <div className="flex items-center gap-2">
                <input
                  ref={photoInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) uploadPhoto(file);
                  }}
                />
                <Button size="sm" onClick={() => photoInputRef.current?.click()} disabled={savingPhoto}>
                  <Upload className="me-1 h-4 w-4" />
                  {t.uploadNewPhoto}
                </Button>
                <Button size="sm" variant="secondary" onClick={resetPhoto} disabled={savingPhoto}>
                  {t.resetPhoto}
                </Button>
              </div>
            </div>

            {loading ? (
              <p className="text-sm text-[#8a8da8]">{t.loading}</p>
            ) : (
              <>
                <div className="grid gap-4 md:grid-cols-2">
                  <Input placeholder={t.name} value={profileForm.name} onChange={(e) => setProfileForm((s) => ({ ...s, name: e.target.value }))} />
                  <Input
                    placeholder={t.email}
                    value={profileForm.email}
                    onChange={(e) => setProfileForm((s) => ({ ...s, email: e.target.value }))}
                  />
                </div>

                <div className="flex items-center justify-between pt-2">
                  <p className="inline-flex items-center gap-2 text-xs text-[#8a8da8]">
                    <Shield className="h-3.5 w-3.5" />
                    {t.roles}:{' '}
                    {profile?.roles?.map((role) => (
                      role === 'super-admin' ? t.superAdmin : role === 'admin' ? t.adminRole : role
                    )).join(', ') || '-'}
                  </p>
                  <Button onClick={updateProfile} disabled={savingProfile || !profileForm.name.trim()}>
                    {t.save}
                  </Button>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      ) : (
        <Card className="shadow-[0_10px_24px_rgba(47,43,61,0.08)] dark:shadow-[0_10px_24px_rgba(0,0,0,0.25)]">
          <CardContent className="space-y-4 p-5">
            <h3 className="text-lg font-semibold">{t.changePassword}</h3>
            <div className="grid gap-4 md:grid-cols-2">
              <Input
                type="password"
                placeholder={t.currentPassword}
                value={passwordForm.current_password}
                onChange={(e) => setPasswordForm((s) => ({ ...s, current_password: e.target.value }))}
              />
              <div />
              <Input
                type="password"
                placeholder={t.newPassword}
                value={passwordForm.new_password}
                onChange={(e) => setPasswordForm((s) => ({ ...s, new_password: e.target.value }))}
              />
              <Input
                type="password"
                placeholder={t.confirmNewPassword}
                value={passwordForm.new_password_confirmation}
                onChange={(e) => setPasswordForm((s) => ({ ...s, new_password_confirmation: e.target.value }))}
              />
            </div>
            <div className="flex justify-end">
              <Button onClick={updatePassword} disabled={savingPassword}>
                {t.save}
              </Button>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
