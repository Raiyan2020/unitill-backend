import axios from 'axios';

export function getApiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; data?: unknown; errors?: Record<string, string[] | string> }
      | undefined;

    if (typeof data?.message === 'string' && data.message.trim() !== '') {
      return data.message;
    }

    if (data?.errors && typeof data.errors === 'object') {
      const firstKey = Object.keys(data.errors)[0];
      if (firstKey) {
        const firstValue = data.errors[firstKey];
        if (Array.isArray(firstValue) && firstValue.length > 0) {
          return String(firstValue[0]);
        }
        if (typeof firstValue === 'string' && firstValue.trim() !== '') {
          return firstValue;
        }
      }
    }

    if (typeof error.message === 'string' && error.message.trim() !== '') {
      return error.message;
    }
  }

  if (error instanceof Error && error.message.trim() !== '') {
    return error.message;
  }

  return fallback;
}
