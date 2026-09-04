import { FormEvent, KeyboardEvent, useEffect, useMemo, useRef, useState } from 'react';
import { BsCheck, BsCheckAll } from 'react-icons/bs';
import { FaPaperclip, FaRegSmile } from 'react-icons/fa';
import { FaMicrophone } from 'react-icons/fa6';
import { HiDotsVertical } from 'react-icons/hi';
import { IoArrowBack, IoSend } from 'react-icons/io5';
import type { SupportMessage } from '../types';
import { getAvatarColor, getInitials } from '../lib/avatarUtils';

interface ChatPanelProps {
  user: string;
  mobile: string;
  messages: SupportMessage[];
  status: string;
  loading: boolean;
  sending: boolean;
  error: string;
  mobileVisible: boolean;
  onBack: () => void;
  onSend: (text: string) => Promise<void>;
}

function messageStatus(msg: SupportMessage) {
  if (!msg.is_own) return null;
  if (msg.seen_by_user) {
    return <BsCheckAll className="h-4 w-4 text-blue-400" />;
  }
  return <BsCheck className="h-4 w-4 text-gray-300" />;
}

function dayLabel(msg: SupportMessage, prev?: SupportMessage): string | null {
  if (!msg.date) return null;
  if (!prev || prev.date !== msg.date) return msg.date;
  return null;
}

function clusterRadius(isOwn: boolean, pos: 'single' | 'top' | 'mid' | 'bot') {
  const own = {
    single: 'rounded-2xl rounded-br-md',
    top: 'rounded-2xl rounded-br-sm',
    mid: 'rounded-xl rounded-r-sm',
    bot: 'rounded-2xl rounded-tr-md',
  };
  const other = {
    single: 'rounded-2xl rounded-bl-md',
    top: 'rounded-2xl rounded-bl-sm',
    mid: 'rounded-xl rounded-l-sm',
    bot: 'rounded-2xl rounded-tl-md',
  };
  return (isOwn ? own : other)[pos];
}

function clusterPos(messages: SupportMessage[], index: number): 'single' | 'top' | 'mid' | 'bot' {
  const cur = messages[index];
  if (!cur) return 'single';
  const prev = index > 0 ? messages[index - 1] : null;
  const next = index < messages.length - 1 ? messages[index + 1] : null;
  const sameSender = (a: SupportMessage | null | undefined, b: SupportMessage) =>
    !!a && a.is_own === b.is_own && a.date === b.date;
  const closeInTime = (a: SupportMessage | null | undefined, b: SupportMessage) =>
    !!a && Math.abs(a.timestamp - b.timestamp) <= 600;
  const samePrev = sameSender(prev, cur) && closeInTime(prev, cur);
  const sameNext = sameSender(next, cur) && closeInTime(next, cur);
  if (samePrev && sameNext) return 'mid';
  if (samePrev) return 'bot';
  if (sameNext) return 'top';
  return 'single';
}

export function ChatPanel({
  user,
  mobile,
  messages,
  status,
  loading,
  sending,
  error,
  mobileVisible,
  onBack,
  onSend,
}: ChatPanelProps) {
  const [draft, setDraft] = useState('');
  const endRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, user]);

  const grouped = useMemo(() => messages, [messages]);

  if (!user) {
    return (
      <section
        className={`flex flex-1 items-center justify-center bg-[#101010] text-gray-500 ${
          mobileVisible ? 'hidden md:flex' : 'flex'
        }`}
      >
        <div className="text-center">
          <div className="mb-2 text-5xl">💬</div>
          <p className="text-lg">یک گفتگو را انتخاب کنید</p>
        </div>
      </section>
    );
  }

  async function submit(e?: FormEvent) {
    e?.preventDefault();
    const text = draft.trim();
    if (!text || sending) return;
    setDraft('');
    if (textareaRef.current) textareaRef.current.style.height = '44px';
    await onSend(text);
  }

  function onKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  }

  return (
    <section
      className={`flex min-w-0 flex-1 flex-col bg-[#101010] text-white ${
        mobileVisible ? 'hidden md:flex' : 'flex'
      }`}
    >
      <header className="flex h-14 items-center justify-between border-b border-[#212121] bg-[#212121] px-2 md:px-4">
        <div className="flex min-w-0 items-center gap-3">
          <button
            type="button"
            onClick={onBack}
            className="rounded-full p-2 text-gray-400 hover:bg-gray-700 md:hidden"
          >
            <IoArrowBack className="h-5 w-5" />
          </button>
          <div
            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white ${getAvatarColor(
              user,
            )}`}
          >
            {getInitials(user)}
          </div>
          <div className="min-w-0">
            <h1 className="truncate text-base font-medium">{user}</h1>
            <p className="truncate text-xs text-gray-400">
              {mobile && mobile !== '-' ? mobile : status || 'پشتیبانی'}
            </p>
          </div>
        </div>
        <button type="button" className="rounded-full p-2 text-gray-400 hover:bg-gray-700">
          <HiDotsVertical className="h-5 w-5" />
        </button>
      </header>

      <div className="tg-chat-bg flex-1 overflow-y-auto p-4 tg-scroll">
        {loading && messages.length === 0 ? (
          <div className="py-8 text-center text-sm text-gray-500">در حال بارگذاری پیام‌ها...</div>
        ) : null}

        {error ? (
          <div className="mb-3 rounded-xl bg-red-900/40 px-3 py-2 text-sm text-red-200">{error}</div>
        ) : null}

        <div className="flex flex-col space-y-1">
          {grouped.map((msg, index) => {
            const separator = dayLabel(msg, grouped[index - 1]);
            const pos = clusterPos(grouped, index);
            return (
              <div key={msg.id} className="space-y-1">
                {separator ? (
                  <div className="my-4 flex justify-center">
                    <span className="rounded-full bg-gray-800 px-3 py-1 text-sm text-gray-400">
                      {separator}
                    </span>
                  </div>
                ) : null}
                <div className={`flex ${msg.is_own ? 'justify-start' : 'justify-end'}`}>
                  <div
                    className={`max-w-[min(85%,22rem)] px-3 py-2 ${clusterRadius(msg.is_own, pos)} ${
                      msg.is_own ? 'bg-purple-500 text-white' : 'bg-gray-700 text-white'
                    }`}
                  >
                    {msg.image ? (
                      <img src={msg.image} alt="" className="mb-2 max-h-56 rounded-lg object-cover" />
                    ) : null}
                    {msg.text ? <div className="whitespace-pre-wrap break-words text-sm">{msg.text}</div> : null}
                    <div className="mt-1 flex items-center justify-end gap-1 text-[11px] opacity-70">
                      <span>{msg.time}</span>
                      {msg.edited ? <span>· ویرایش</span> : null}
                      {messageStatus(msg)}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
        <div ref={endRef} />
      </div>

      <form onSubmit={submit} className="border-t border-[#212121]/50 bg-[#101010]/95 p-4 backdrop-blur-sm">
        <div className="flex items-end gap-2">
          <button type="button" className="rounded-lg p-2 text-gray-400 hover:bg-gray-800">
            <FaPaperclip className="h-5 w-5" />
          </button>

          <div className="relative flex-1">
            <textarea
              ref={textareaRef}
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={onKeyDown}
              rows={1}
              placeholder="پیام..."
              className="max-h-[120px] min-h-[44px] w-full resize-none rounded-2xl border-0 bg-[#212121] px-3 py-3 pl-10 text-white outline-none placeholder:text-[#a2acb4]"
              onInput={(e) => {
                const el = e.currentTarget;
                el.style.height = 'auto';
                el.style.height = `${el.scrollHeight}px`;
              }}
            />
            <button type="button" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
              <FaRegSmile className="h-5 w-5" />
            </button>
          </div>

          <div className="relative h-10 w-10 shrink-0">
            <button
              type="button"
              disabled={!!draft.trim()}
              className={`absolute inset-0 rounded-full bg-blue-500 p-2 text-white transition ${
                draft.trim() ? 'scale-75 opacity-0' : 'scale-100 opacity-100'
              }`}
            >
              <FaMicrophone className="h-6 w-6" />
            </button>
            <button
              type="submit"
              disabled={sending || !draft.trim()}
              className={`absolute inset-0 rounded-full bg-blue-500 p-2 text-white transition hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-40 ${
                draft.trim() ? 'scale-100 opacity-100' : 'scale-75 opacity-0'
              }`}
            >
              <IoSend className="h-6 w-6" />
            </button>
          </div>
        </div>
      </form>
    </section>
  );
}
