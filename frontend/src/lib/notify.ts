import { App as AntdApp } from 'antd';
import { getApiErrorMessage } from './http-error';

export function useNotify() {
  const { notification } = AntdApp.useApp();

  const success = (message: string) => {
    notification.success({
      message,
      placement: 'topRight',
      duration: 2.4,
      className: 'rs-notify rs-notify-success',
    });
  };

  const error = (message: string) => {
    notification.error({
      message,
      placement: 'topRight',
      duration: 3,
      className: 'rs-notify rs-notify-error',
    });
  };

  const errorFrom = (err: unknown, fallback: string) => {
    error(getApiErrorMessage(err, fallback));
  };

  return { success, error, errorFrom };
}
