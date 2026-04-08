import { BrowserRouter } from 'react-router-dom';
import { App as AntdApp } from 'antd';
import App from './App';
import { I18nProvider } from './providers/i18n-provider';

export default function RootApp() {
  return (
    <I18nProvider>
      <AntdApp>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </AntdApp>
    </I18nProvider>
  );
}
