import { BrowserRouter } from 'react-router-dom';
import { App as AntdApp } from 'antd';
import App from './App';
import { I18nProvider } from './providers/i18n-provider';

export default function RootApp() {
  return (
    <I18nProvider>
      <AntdApp>
        {/* Laravel serves this SPA from /admin/*, so its routes ("/login", "/users")
            are relative to that prefix. Without the basename every route misses and
            the catch-all bounces to "/", which the backend 404s. */}
        <BrowserRouter basename="/admin">
          <App />
        </BrowserRouter>
      </AntdApp>
    </I18nProvider>
  );
}
