import { Dialog } from '../dialog';

interface ApiResult {
  status: boolean;
  message?: string;
}

/**
 * JSON request helper. The server reports failures with a 500 status and a message, which the global
 * jQuery handlers would swallow, so this uses fetch and shows the server's message instead.
 * Resolves to null after showing an error dialog.
 */
export async function api<T extends ApiResult>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', url: string, body?: unknown): Promise<T | null> {
  try {
    const response = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.Laravel.csrfToken,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => null) as (T & { errors?: Record<string, string[]> }) | null;
    if (response.ok && data?.status) return data;

    let message = data?.message;
    if (data?.errors) message = Object.values(data.errors).map(e => e[0]).join('<br>');
    Dialog.fail(undefined, message || window.Laravel.ajaxErrors[response.status] || window.Laravel.ajaxErrors[500]);
  } catch {
    Dialog.fail(undefined, window.Laravel.ajaxErrors[500]);
  }
  return null;
}
