import { BrowserRouter } from 'react-router-dom';
import { App as AntdApp } from 'antd';
import App from './App';
import { I18nProvider, useI18n } from './providers/i18n-provider';

/**
 * Keyed on the locale so switching language remounts the tree.
 *
 * Half of what the dashboard shows is DB-backed text the API localizes from the
 * `lang` header. Without a remount those values keep whatever language they were
 * fetched in until the next filter change, so the page ends up half-translated.
 */
function LocalizedApp() {
  const { locale } = useI18n();

  return (
    <AntdApp key={locale}>
      {/* Laravel serves the SPA at /admin, so client routes are relative to it. */}
      <BrowserRouter basename="/admin">
        <App />
      </BrowserRouter>
    </AntdApp>
  );
}

export default function RootApp() {
  return (
    <I18nProvider>
      <LocalizedApp />
    </I18nProvider>
  );
}
