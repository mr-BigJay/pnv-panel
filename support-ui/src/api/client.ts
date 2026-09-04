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

export function createAdminSupportApi(config: SupportConfig) {
  const base = config.apiUrl;

  return {
    role: 'admin' as const,

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

    async messages(user: string, since = 0, sync = false): Promise<MessagesResponse> {
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

    async send(
      user: string,
      message: string,
      csrf: string,
      replyTo = '',
    ): Promise<{ ok: boolean; message?: SupportMessage; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', user, message, csrf, reply_to: replyTo }),
      });
      return parseJson(res);
    },

    async edit(
      user: string,
      editId: string,
      text: string,
      csrf: string,
    ): Promise<{ ok: boolean; message?: SupportMessage; edited?: boolean; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'edit',
          user,
          edit_id: editId,
          edit_text: text,
          csrf,
        }),
      });
      return parseJson(res);
    },

    async deleteMessage(
      user: string,
      messageId: string,
      csrf: string,
    ): Promise<{ ok: boolean; deleted?: boolean; message_id?: string; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'delete',
          user,
          delete_id: messageId,
          csrf,
        }),
      });
      return parseJson(res);
    },

    async sendVoice(
      user: string,
      blob: Blob,
      csrf: string,
    ): Promise<{ ok: boolean; message?: SupportMessage; error?: string }> {
      const ext = blob.type.includes('ogg') ? 'ogg' : 'webm';
      const fd = new FormData();
      fd.append('action', 'send_voice');
      fd.append('user', user);
      fd.append('csrf', csrf);
      fd.append('voice', blob, `voice.${ext}`);
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
      });
      return parseJson(res);
    },
  };
}

function createUserSupportApi(config: SupportConfig) {
  const base = config.apiUrl;

  return {
    role: 'user' as const,

    async bootstrap(): Promise<BootstrapResponse> {
      const res = await fetch(apiUrl(base, { action: 'bootstrap' }), {
        credentials: 'same-origin',
      });
      return parseJson(res);
    },

    async messages(since = 0, sync = false): Promise<MessagesResponse> {
      const res = await fetch(
        apiUrl(base, {
          action: 'messages',
          since,
          sync: sync ? 1 : undefined,
        }),
        { credentials: 'same-origin' },
      );
      return parseJson(res);
    },

    async send(
      message: string,
      csrf: string,
      replyTo = '',
    ): Promise<{ ok: boolean; message?: SupportMessage; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', message, csrf, reply_to: replyTo }),
      });
      return parseJson(res);
    },

    async edit(
      editId: string,
      text: string,
      csrf: string,
    ): Promise<{ ok: boolean; message?: SupportMessage; edited?: boolean; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'edit',
          edit_id: editId,
          edit_text: text,
          csrf,
        }),
      });
      return parseJson(res);
    },

    async deleteMessage(
      messageId: string,
      csrf: string,
    ): Promise<{ ok: boolean; deleted?: boolean; message_id?: string; error?: string }> {
      const res = await fetch(base, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'delete',
          delete_id: messageId,
          csrf,
        }),
      });
      return parseJson(res);
    },
  };
}

export function createSupportApi(config: SupportConfig) {
  if (config.role === 'user') {
    return createUserSupportApi(config);
  }
  return createAdminSupportApi(config);
}

export type SupportApi = ReturnType<typeof createSupportApi>;
export type AdminSupportApi = ReturnType<typeof createAdminSupportApi>;
export type UserSupportApi = ReturnType<typeof createUserSupportApi>;
