import { Bell, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Input } from '../components/ui/input';
import { TableFooter, TableLoadingRow } from '../components/table/TableHelpers';
import { api } from '../lib/api';
import { ensureApiSuccess } from '../lib/api-response';
import { useNotify } from '../lib/notify';
import { useI18n } from '../providers/i18n-provider';

type HistoryRow = {
  id: number;
  audience: 'all' | 'user';
  topic: string | null;
  title: string;
  body: string;
  status: string;
  recipients_count: number | null;
  error_message: string | null;
  admin_name: string;
  user_id: number | null;
  user_name: string | null;
  user_email: string | null;
  created_at: string | null;
};

type Meta = {
  all_users_topic: string;
  estimated_all_audience: number;
  firebase_configured: boolean;
};

type PaginatedResponse<T> = { data: T[]; total: number };

export function PushNotificationsPage() {
  const { t } = useI18n();
  const notify = useNotify();
  const [rows, setRows] = useState<HistoryRow[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [total, setTotal] = useState(0);
  const [audience, setAudience] = useState<'all' | 'user'>('all');
  const [userId, setUserId] = useState('');
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [link, setLink] = useState('');
  const didInitSearch = useRef(false);

  const fetchMeta = async () => {
    try {
      const res = await api.get('/admin/push-notifications/meta');
      setMeta(ensureApiSuccess<Meta>(res, 'Failed to load notification meta'));
    } catch (error) {
      notify.errorFrom(error, 'Failed to load notification meta.');
    }
  };

  const fetchRows = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/push-notifications', {
        params: { page, per_page: pageSize, search: search || undefined },
      });
      const payload = ensureApiSuccess<PaginatedResponse<HistoryRow>>(res, 'Failed to load notifications');
      setRows(payload?.data || []);
      setTotal(payload?.total || 0);
    } catch (error) {
      notify.errorFrom(error, 'Failed to load notifications.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMeta();
  }, []);

  useEffect(() => {
    fetchRows();
  }, [page, pageSize]);

  useEffect(() => {
    if (!didInitSearch.current) {
      didInitSearch.current = true;
      return;
    }
    const timer = setTimeout(() => {
      setPage(1);
      fetchRows();
    }, 350);
    return () => clearTimeout(timer);
  }, [search]);

  const handleSend = async () => {
    if (!title.trim() || !body.trim()) {
      notify.error('Title and body are required.');
      return;
    }
    if (audience === 'user' && !userId.trim()) {
      notify.error('User ID is required for single-user notifications.');
      return;
    }

    setSending(true);
    try {
      const res = await api.post('/admin/push-notifications', {
        audience,
        user_id: audience === 'user' ? Number(userId) : undefined,
        title: title.trim(),
        body: body.trim(),
        link: link.trim() || undefined,
      });
      ensureApiSuccess(res, 'Failed to send notification');
      notify.success(audience === 'all' ? 'Notification sent to all users.' : 'Notification sent to user.');
      setTitle('');
      setBody('');
      setLink('');
      setUserId('');
      fetchRows();
      fetchMeta();
    } catch (error) {
      notify.errorFrom(error, 'Failed to send notification.');
    } finally {
      setSending(false);
    }
  };

  const pagesCount = Math.max(1, Math.ceil(total / pageSize));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-2xl font-semibold text-[#2f2b3d] dark:text-[#d7d8ea]">{t.pushNotifications}</h2>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <Send size={18} />
            {t.sendNotification}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {meta ? (
            <div className="rounded-lg border border-[#ececf3] bg-[#fafafe] p-3 text-sm dark:border-[#44485f] dark:bg-[#383d56]">
              <p><strong>Topic:</strong> {meta.all_users_topic}</p>
              <p><strong>Estimated audience:</strong> {meta.estimated_all_audience}</p>
              <p><strong>Firebase:</strong> {meta.firebase_configured ? 'Configured' : 'Not configured'}</p>
            </div>
          ) : null}

          <div className="grid gap-4 md:grid-cols-2">
            <label className="space-y-2 text-sm">
              <span className="font-medium">{t.audience}</span>
              <select
                value={audience}
                onChange={(e) => setAudience(e.target.value as 'all' | 'user')}
                className="h-10 w-full rounded-lg border border-[#dbdbe8] bg-white px-3 dark:border-[#4a4f68] dark:bg-[#2f3349]"
              >
                <option value="all">{t.sendToAll}</option>
                <option value="user">{t.sendToUser}</option>
              </select>
            </label>

            {audience === 'user' ? (
              <label className="space-y-2 text-sm">
                <span className="font-medium">User ID</span>
                <Input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="42" />
              </label>
            ) : null}
          </div>

          <label className="block space-y-2 text-sm">
            <span className="font-medium">{t.notificationTitle}</span>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" />
          </label>

          <label className="block space-y-2 text-sm">
            <span className="font-medium">{t.notificationBody}</span>
            <textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={4}
              className="w-full rounded-lg border border-[#dbdbe8] bg-white px-3 py-2 dark:border-[#4a4f68] dark:bg-[#2f3349]"
              placeholder="Message body"
            />
          </label>

          <label className="block space-y-2 text-sm">
            <span className="font-medium">Link (optional)</span>
            <Input value={link} onChange={(e) => setLink(e.target.value)} placeholder="https://..." />
          </label>

          <Button onClick={handleSend} disabled={sending || meta?.firebase_configured === false}>
            <Bell size={16} className="me-2" />
            {sending ? 'Sending...' : t.sendNotification}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          <div className="flex items-center justify-between gap-3 border-b border-[#ececf3] p-4 dark:border-[#44485f]">
            <h3 className="font-semibold">History</h3>
            <div className="flex items-center gap-2">
              <select
                value={pageSize}
                onChange={(e) => { setPage(1); setPageSize(Number(e.target.value)); }}
                className="h-9 rounded-lg border border-[#dbdbe8] bg-white px-2 text-sm dark:border-[#4a4f68] dark:bg-[#2f3349]"
              >
                {[10, 25, 50].map((size) => <option key={size} value={size}>{size}</option>)}
              </select>
              <Input className="h-9 w-[220px]" placeholder="Search" value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-[#f8f7fb] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b6b8cc]">
                <tr>
                  <th className="px-4 py-3 text-start">ID</th>
                  <th className="px-4 py-3 text-start">Audience</th>
                  <th className="px-4 py-3 text-start">Title</th>
                  <th className="px-4 py-3 text-start">User</th>
                  <th className="px-4 py-3 text-start">Status</th>
                  <th className="px-4 py-3 text-start">Recipients</th>
                  <th className="px-4 py-3 text-start">Date</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <TableLoadingRow colSpan={7} />
                ) : rows.length === 0 ? (
                  <tr><td className="px-4 py-6 text-center text-[#8a8da8]" colSpan={7}>{t.noDataFound}</td></tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-t border-[#ececf3] dark:border-[#44485f]">
                      <td className="px-4 py-3">{row.id}</td>
                      <td className="px-4 py-3">{row.audience === 'all' ? `Topic: ${row.topic || '-'}` : 'User'}</td>
                      <td className="px-4 py-3">{row.title}</td>
                      <td className="px-4 py-3">{row.user_name ? `${row.user_name} (#${row.user_id})` : '-'}</td>
                      <td className="px-4 py-3">{row.status}</td>
                      <td className="px-4 py-3">{row.recipients_count ?? '-'}</td>
                      <td className="px-4 py-3">{row.created_at || '-'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          <TableFooter
            page={page}
            pageSize={pageSize}
            total={total}
            onPrev={() => setPage((p) => p - 1)}
            onNext={() => setPage((p) => p + 1)}
            prevDisabled={page <= 1}
            nextDisabled={page >= pagesCount}
          />
        </CardContent>
      </Card>
    </div>
  );
}
