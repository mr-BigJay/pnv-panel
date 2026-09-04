import { useEffect, useMemo, useRef } from 'react';
import { BsCheck, BsCheckAll } from 'react-icons/bs';
import { HiDotsVertical } from 'react-icons/hi';
import { IoArrowBack } from 'react-icons/io5';
import type { SupportMessage } from '../types';
import { getAvatarColor, getInitials } from '../lib/avatarUtils';
import { formatPersianDate, formatPersianTime, toPersianDigits } from '../lib/persianDigits';
import { MessageComposer } from './MessageComposer';

interface ChatPanelProps {
  user: string;
  mobile: string;
  messages: SupportMessage[];
  status: string;
  loading: boolean;
  sending: boolean;
  error: string;
  draft: string;
  mobileVisible: boolean;
  onDraftChange: (value: string) => void;
  onBack: () => void;
  onSendText: () => Promise<void>;
  onSendVoice: (blob: Blob) => Promise<void>;
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
  if (!prev || prev.date !== msg.date) return formatPersianDate(msg.date);
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
  draft,
  mobileVisible,
  onDraftChange,
  onBack,
  onSendText,
  onSendVoice,
}: ChatPanelProps) {
  const endRef = useRef<HTMLDivElement>(null);
  const grouped = useMemo(() => messages, [messages]);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, user]);

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

  return (
    <section
      className={`flex min-w-0 flex-1 flex-col bg-[#101010] text-white ${
        mobileVisible ? 'hidden md:flex' : 'flex'
      }`}
    >
      <header className="flex h-14 shrink-0 items-center justify-between border-b border-[#212121] bg-[#212121] px-2 md:px-4">
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
            <p className="fa-num truncate text-xs text-gray-400">
              {mobile && mobile !== '-' ? toPersianDigits(mobile) : status || 'پشتیبانی'}
            </p>
          </div>
        </div>
        <button type="button" className="rounded-full p-2 text-gray-400 hover:bg-gray-700">
          <HiDotsVertical className="h-5 w-5" />
        </button>
      </header>

      <div className="tg-chat-bg min-h-0 flex-1 overflow-y-auto p-4 tg-scroll">
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
                    <span className="fa-num rounded-full bg-gray-800 px-3 py-1 text-sm text-gray-400">
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
                    {msg.audio ? (
                      <audio controls preload="metadata" src={msg.audio} className="mb-2 w-full min-w-[200px]" />
                    ) : null}
                    {msg.text ? <div className="whitespace-pre-wrap break-words text-sm">{msg.text}</div> : null}
                    <div className="fa-num mt-1 flex items-center justify-end gap-1 text-[11px] opacity-70">
                      <span>{formatPersianTime(msg.time)}</span>
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

      <MessageComposer
        draft={draft}
        sending={sending}
        onDraftChange={onDraftChange}
        onSendText={onSendText}
        onSendVoice={onSendVoice}
      />
    </section>
  );
}
