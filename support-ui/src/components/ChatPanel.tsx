import { useEffect, useMemo, useRef } from 'react';
import { BsCheck, BsCheckAll } from 'react-icons/bs';
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
  onOpenSubscriptions: () => void;
  onSendText: (text: string) => Promise<void>;
  onSendVoice: (blob: Blob) => Promise<void>;
}

function messageStatus(msg: SupportMessage) {
  if (!msg.is_own) return null;
  if (msg.seen_by_user) {
    return <BsCheckAll className="h-3.5 w-3.5 text-[#6ab2f2]" />;
  }
  return <BsCheck className="h-3.5 w-3.5 text-white/50" />;
}

function dayLabel(msg: SupportMessage, prev?: SupportMessage): string | null {
  if (!msg.date) return null;
  if (!prev || prev.date !== msg.date) return formatPersianDate(msg.date);
  return null;
}

function clusterRadius(isOwn: boolean, pos: 'single' | 'top' | 'mid' | 'bot') {
  const own = {
    single: 'rounded-xl rounded-br-[4px]',
    top: 'rounded-xl rounded-br-[4px] rounded-tr-xl',
    mid: 'rounded-xl rounded-r-[4px]',
    bot: 'rounded-xl rounded-tr-[4px] rounded-br-xl',
  };
  const other = {
    single: 'rounded-xl rounded-bl-[4px]',
    top: 'rounded-xl rounded-bl-[4px] rounded-tl-xl',
    mid: 'rounded-xl rounded-l-[4px]',
    bot: 'rounded-xl rounded-tl-[4px] rounded-bl-xl',
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
  onOpenSubscriptions,
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
        className={`flex min-w-0 flex-1 flex-col items-center justify-center bg-[#0e1621] text-[#6d8399] ${
          mobileVisible ? 'hidden md:flex' : 'flex'
        }`}
      >
        <div className="text-center">
          <div className="mb-3 text-5xl opacity-80">💬</div>
          <p className="text-base">یک گفتگو را از لیست انتخاب کنید</p>
        </div>
      </section>
    );
  }

  return (
    <section
      className={`flex min-w-0 flex-1 flex-col bg-[#0e1621] text-[#e4ecf4] ${
        mobileVisible ? 'hidden md:flex' : 'flex'
      }`}
    >
      <header className="flex h-[56px] shrink-0 items-center justify-between gap-2 border-b border-[#0e1621] bg-[#17212b] px-2 md:px-3">
        <div className="flex min-w-0 flex-1 items-center gap-2.5">
          <button
            type="button"
            onClick={onBack}
            className="shrink-0 rounded-full p-2 text-[#6ab2f2] hover:bg-[#242f3d] md:hidden"
            aria-label="بازگشت"
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
          <div className="min-w-0 flex-1">
            <h1 className="support-chat-title truncate text-[16px] font-medium leading-snug text-white">
              {user}
            </h1>
            <p className="fa-num truncate text-[12px] leading-normal text-[#8b9cb3]">
              {mobile && mobile !== '-' ? toPersianDigits(mobile) : status || 'پشتیبانی'}
            </p>
          </div>
        </div>
        <button
          type="button"
          onClick={onOpenSubscriptions}
          className="shrink-0 rounded-lg bg-[#242f3d] px-3 py-2 text-[12px] font-medium text-[#6ab2f2] hover:bg-[#2b5278] hover:text-white"
        >
          اشتراک‌ها
        </button>
      </header>

      <div className="tg-chat-bg min-h-0 flex-1 overflow-y-auto px-3 py-3 md:px-4 md:py-4 tg-scroll">
        {loading && messages.length === 0 ? (
          <div className="py-8 text-center text-sm text-[#6d8399]">در حال بارگذاری پیام‌ها...</div>
        ) : null}

        {error ? (
          <div className="mb-3 rounded-xl bg-red-900/40 px-3 py-2 text-sm text-red-200">{error}</div>
        ) : null}

        <div className="flex flex-col gap-0.5">
          {grouped.map((msg, index) => {
            const separator = dayLabel(msg, grouped[index - 1]);
            const pos = clusterPos(grouped, index);
            return (
              <div key={msg.id}>
                {separator ? (
                  <div className="my-3 flex justify-center">
                    <span className="fa-num rounded-full bg-[#182533] px-3 py-1 text-[11px] text-[#8b9cb3]">
                      {separator}
                    </span>
                  </div>
                ) : null}
                <div className={`flex ${msg.is_own ? 'justify-start' : 'justify-end'}`}>
                  <div
                    className={`max-w-[min(82%,24rem)] ${msg.image && !msg.text && !msg.audio ? 'p-1' : 'px-3 py-2'} ${clusterRadius(msg.is_own, pos)} ${
                      msg.is_own ? 'tg-bubble-admin' : 'tg-bubble-user'
                    }`}
                  >
                    {msg.image ? (
                      <a
                        href={msg.image}
                        target="_blank"
                        rel="noreferrer"
                        className="tg-msg-image-link block overflow-hidden rounded-[10px]"
                      >
                        <img
                          src={msg.image}
                          alt=""
                          loading="lazy"
                          className="tg-msg-image block max-h-[360px] w-full max-w-[280px] object-contain"
                        />
                      </a>
                    ) : null}
                    {msg.audio ? (
                      <audio controls preload="metadata" src={msg.audio} className="mb-2 w-full min-w-[200px]" />
                    ) : null}
                    {msg.text ? (
                      <div className={`whitespace-pre-wrap break-words text-[14px] leading-relaxed ${msg.image ? 'mt-2' : ''}`}>
                        {msg.text}
                      </div>
                    ) : null}
                    <div
                      className={`fa-num mt-1 flex items-center justify-end gap-1 text-[10px] ${
                        msg.is_own ? 'text-white/55' : 'text-[#6d8399]'
                      }`}
                    >
                      <span>{formatPersianTime(msg.time)}</span>
                      {msg.edited ? <span>· ویرایش‌شده</span> : null}
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
