import axios from 'axios';
import { clearAuthToken, getAuthToken } from './auth';

export const api = axios.create({
  // One origin serves both the dashboard and Laravel API. Vite proxies this
  // relative path during development and Laravel handles it in production.
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
});

export const backendOrigin = window.location.origin.replace(/\/+$/, '');

api.interceptors.request.use((config) => {
  const token = getAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  // DB-backed text — category names, attribute labels and their options, city
  // and country names, report reasons — is localized by the API from this
  // header. Without it every one of those came back in the default language
  // however the dashboard was set. Read from storage rather than the provider
  // so this stays a plain module with no React dependency.
  const locale = localStorage.getItem('unitill_locale');
  config.headers.lang = locale === 'ar' ? 'ar' : 'en';

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      clearAuthToken();

      if (window.location.pathname !== '/login') {
        window.location.replace('/login');
      }
    }

    return Promise.reject(error);
  },
);
