const apiScope = String(import.meta.env.VITE_API_BASE_URL || '/api')
  .replace(/[^a-z0-9]+/gi, '_')
  .replace(/^_+|_+$/g, '')
  .toLowerCase();
const TOKEN_KEY = `unitill_admin_token:${apiScope}`;
const ADMIN_KEY = `unitill_admin_info:${apiScope}`;

export function setAuthToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token);
}

export type AdminAuthInfo = {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
};

export function setAdminAuthInfo(admin: AdminAuthInfo) {
  localStorage.setItem(ADMIN_KEY, JSON.stringify(admin));
}

export function getAdminAuthInfo(): AdminAuthInfo | null {
  const raw = localStorage.getItem(ADMIN_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as AdminAuthInfo;
  } catch {
    return null;
  }
}

export function hasPermission(permission: string) {
  const info = getAdminAuthInfo();
  if (!info) return false;
  return Array.isArray(info.permissions) && info.permissions.includes(permission);
}

export function getAuthToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function clearAuthToken() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(ADMIN_KEY);
}

export function isAuthenticated() {
  return Boolean(getAuthToken());
}
