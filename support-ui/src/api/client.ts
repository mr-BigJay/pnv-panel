import type {
  BootstrapResponse,
  MessagesResponse,
  SupportConfig,
  SupportMessage,
  TicketsResponse,
} from '../types';

async function parseJson<T>(res: Response): Promise<T> {
  const raw = await res.text();

  if (!raw.trim()) {
    throw new Error(
      `پاسخ API خالی است (HTTP ${res.status}) — احتمالاً support_lib.php روی سرور deploy نشده`,
    );
  }

  let data: unknown;

  try {
    data = JSON.parse(raw);
  } catch {
    throw new Error(`پاسخ API نامعتبر است (HTTP ${res.status})`);
  }

  if (!res.ok) {
    const err = (data as { error?: string }).error ?? `HTTP ${res.status}`;
    throw new Error(err);
  }

  return data as T;
}

function apiUrl(base: string, params: Record<string, string | number | boolean | undefined>): string {
  const url = new URL(base, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });
  return url.pathname + url.search;
}

export function createSupportApi(config: SupportConfig) {
  const base = config.apiUrl;

  return {
    async bootstrap(): Promise<BootstrapResponse> {
      const res = await fetch(apiUrl(base, { action: 'bootstrap' }), {
        credentials: 'same-origin',
      });
      return parseJson(res);
    },

    async tickets(): Promise<TicketsResponse> {
      const res = await fetch(apiUrl(base, { action: 'tickets' }), {
        credentials: 'same-origin',
      });
      return parseJson(res);
    },

    async messages(
      user: string,
      since = 0,
      sync = false,
    ): Promise<MessagesResponse> {
      const res = await fetch(
        apiUrl(base, {
          action: 'messages',
          user,
          since,
          sync: sync ? 1 : undefined,
        }),
        { credentials: 'same-origin' },
      );
      return parseJson(res);
    },

    async send(user: string, message: string, csrf: string): Promise<{ ok: boolean; message?: SupportMessage; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', user, message, csrf }),
      });
      return parseJson(res);
    },
  };
}

export type SupportApi = ReturnType<typeof createSupportApi>;
