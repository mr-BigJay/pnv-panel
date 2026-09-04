import { FormEvent, useState } from 'react';
import type { SupportMessage } from '../types';

interface ChatPanelProps {
  user: string;
  messages: SupportMessage[];
  status: string;
  loading: boolean;
  sending: boolean;
  error: string;
  onSend: (text: string) => Promise<void>;
}

export function ChatPanel({
  user,
  messages,
  status,
  loading,
  sending,
  error,
  onSend,
}: ChatPanelProps) {
  const [draft, setDraft] = useState('');

  if (!user) {
    return (
      <section className="flex flex-1 items-center justify-center bg-tg-panel text-tg-muted">
        <div className="text-center">
          <div className="mb-2 text-4xl">👈</div>
          <p>یک کاربر را از لیست انتخاب کنید</p>
        </div>
      </section>
    );
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const text = draft.trim();
    if (!text || sending) return;
    setDraft('');
    await onSend(text);
  }

  return (
    <section className="flex min-w-0 flex-1 flex-col bg-tg-panel">
      <header className="flex items-center justify-between border-b border-tg-border px-4 py-3">
        <div>
          <h3 className="font-semibold">{user}</h3>
          {status ? (
            <p className="text-xs text-tg-muted">وضعیت: {status}</p>
          ) : null}
        </div>
      </header>

      <div className="flex-1 space-y-2 overflow-y-auto px-4 py-4">
        {loading && messages.length === 0 ? (
          <div className="text-center text-sm text-tg-muted">در حال بارگذاری پیام‌ها...</div>
        ) : null}

        {error ? (
          <div className="rounded-lg bg-red-900/40 px-3 py-2 text-sm text-red-200">{error}</div>
        ) : null}

        {messages.map((msg) => (
          <div
            key={msg.id}
            className={`flex ${msg.is_own ? 'justify-start' : 'justify-end'}`}
          >
            <div
              className={`max-w-[75%] rounded-2xl px-3 py-2 text-sm leading-relaxed ${
                msg.is_own
                  ? 'rounded-br-md bg-tg-bubbleOwn'
                  : 'rounded-bl-md bg-tg-bubbleOther'
              }`}
            >
              {msg.image ? (
                <img
                  src={msg.image}
                  alt=""
                  className="mb-2 max-h-48 rounded-lg object-cover"
                />
              ) : null}
              {msg.text ? <p className="whitespace-pre-wrap break-words">{msg.text}</p> : null}
              <div className="mt-1 flex items-center justify-end gap-1 text-[11px] text-white/60">
                <span>{msg.time}</span>
                {msg.edited ? <span>· ویرایش</span> : null}
                {msg.is_own ? (
                  <span>{msg.seen_by_user ? '✓✓' : '✓'}</span>
                ) : null}
              </div>
            </div>
          </div>
        ))}
      </div>

      <form
        onSubmit={handleSubmit}
        className="border-t border-tg-border px-4 py-3"
      >
        <div className="flex items-end gap-2">
          <textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            rows={1}
            placeholder="پیام..."
            className="max-h-32 min-h-[42px] flex-1 resize-none rounded-xl border border-tg-border bg-tg-sidebar px-3 py-2 text-sm outline-none focus:border-tg-accent"
          />
          <button
            type="submit"
            disabled={sending || !draft.trim()}
            className="rounded-xl bg-tg-accent px-4 py-2 text-sm font-semibold text-tg-bg disabled:opacity-50"
          >
            {sending ? '...' : 'ارسال'}
          </button>
        </div>
      </form>
    </section>
  );
}
