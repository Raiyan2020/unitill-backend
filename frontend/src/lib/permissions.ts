import type { Dictionary } from '../providers/i18n-provider';

/**
 * Permission names arrive from the API as raw `group.action` keys
 * ("users.create"). Both halves map onto dictionary keys so the UI can show
 * them in the dashboard language instead of the raw string.
 */
const GROUP_KEYS: Record<string, keyof Dictionary> = {
  dashboard: 'dashboard',
  users: 'users',
  admins: 'admins',
  roles: 'roles',
  permissions: 'permissions',
  countries: 'countries',
  cities: 'cities',
  universities: 'universities',
  payment_methods: 'paymentMethods',
  languages: 'languages',
  legal_affairs: 'legalAffairs',
  contact_reasons: 'contactReasons',
  contact_us: 'contactUs',
  categories: 'categories',
  subcategories: 'subCategories',
  notifications: 'pushNotifications',
  ad_reports: 'adReports',
  chat_reports: 'chatReports',
  coupons: 'coupons',
  ads: 'ads',
  settings: 'settings',
  trusted_sellers: 'userVerifications',
};

const ACTION_KEYS: Record<string, keyof Dictionary> = {
  view: 'view',
  create: 'create',
  update: 'update',
  delete: 'delete',
};

/** The page a permission belongs to, translated. Falls back to the raw group. */
export function permissionGroupLabel(name: string, t: Dictionary): string {
  const group = name.split('.')[0] ?? name;
  const key = GROUP_KEYS[group];

  return key ? t[key] : humanize(group);
}

/** A whole permission, e.g. "إنشاء المستخدمون" / "Create Users". */
export function permissionLabel(name: string, t: Dictionary): string {
  const [group, action] = name.split('.');
  const groupKey = GROUP_KEYS[group];
  const actionKey = ACTION_KEYS[action];

  if (!groupKey || !actionKey) return humanize(name);

  return `${t[actionKey]} ${t[groupKey]}`;
}

/** Permissions bucketed by page, in the order the pages first appear. */
export function groupPermissions(names: string[], t: Dictionary) {
  const groups = new Map<string, { page: string; permissions: string[] }>();

  for (const name of names) {
    const group = name.split('.')[0] ?? name;
    if (!groups.has(group)) {
      groups.set(group, { page: permissionGroupLabel(name, t), permissions: [] });
    }
    groups.get(group)!.permissions.push(permissionLabel(name, t));
  }

  return [...groups.values()];
}

function humanize(value: string): string {
  return value.replace(/[._]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
