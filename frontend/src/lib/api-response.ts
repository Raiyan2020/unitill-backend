type ApiEnvelope<T> = {
  status?: boolean;
  message?: string;
  data?: T;
};

export function ensureApiSuccess<T>(response: { data?: ApiEnvelope<T> }, fallbackMessage = 'Request failed'): T {
  const payload = response.data;

  if (payload?.status === false) {
    throw new Error(payload.message || fallbackMessage);
  }

  return (payload?.data as T) ?? ({} as T);
}
