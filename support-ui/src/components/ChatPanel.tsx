import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { BsCheck, BsCheckAll } from 'react-icons/bs';
import { FiCheck } from 'react-icons/fi';
import { IoArrowBack } from 'react-icons/io5';
import { useMessageMenuOpener } from '../hooks/useMessageMenuOpener';
import { isPinned } from '../lib/messagePins';
import type { ReplyTarget, SupportMessage, SupportRole } from '../types';
import { getAvatarColor, getInitials } from '../lib/avatarUtils';
import { formatPersianDate, formatPersianTime, toPersianDigits } from '../lib/persianDigits';
import {
  MessageContextMenu,
  type MessageContextMenuState,
  type MessageMenuAction,
} from './MessageContextMenu';
import { MessageComposer, type MessageComposerHandle } from './MessageComposer';
import { ImageLightbox } from './ImageLightbox';
import { PinnedMessagesBar } from './PinnedMessagesBar';

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
  pinScope: string;
  pinTick: number;
  replyTarget: ReplyTarget | null;
  editTarget: ReplyTarget | null;
  menuState: MessageContextMenuState | null;
  selectMode: boolean;
  selectedIds: Set<string>;
  composerRef: React.RefObject<MessageComposerHandle | null>;
  onDraftChange: (value: string) => void;
  onClearReply: () => void;
  onClearEdit: () => void;
  onBack: () => void;
  onOpenSubscriptions: () => void;
  onSendText: (text: string) => Promise<void>;
  onSendVoice: (blob: Blob) => Promise<void>;
  onSendImage: (file: File, caption?: string) => Promise<void>;
  onMenuOpen: (state: MessageContextMenuState) => void;
  onMenuClose: () => void;
  onMenuAction: (action: MessageMenuAction, message: SupportMessage) => void;
  onToggleSelect: (id: string) => void;
  onExitSelect: () => void;
  onCopySelected: () => void;
  onUnpin: (id: string) => void;
  viewerRole?: SupportRole;
  showSubscriptions?: boolean;
  showVoice?: boolean;
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
  pinScope,
  pinTick,
  replyTarget,
  editTarget,
  menuState,
  selectMode,
  selectedIds,
  composerRef,
  onDraftChange,
  onClearReply,
  onClearEdit,
  onBack,
  onOpenSubscriptions,
  onSendText,
  onSendVoice,
  onSendImage,
  onMenuOpen,
  onMenuClose,
  onMenuAction,
  onToggleSelect,
  onExitSelect,
  onCopySelected,
  onUnpin,
  viewerRole = 'admin',
  showSubscriptions = true,
  showVoice = true,
}: ChatPanelProps) {
  const endRef = useRef<HTMLDivElement>(null);
  const messageRefs = useRef<Map<string, HTMLDivElement>>(new Map());
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null);
  const grouped = useMemo(() => messages, [messages]);

  const { getBubbleHandlers } = useMessageMenuOpener(onMenuOpen);

  const scrollToMessage = useCallback((id: string) => {
    messageRefs.current.get(id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, []);

  useEffect(() => {
    if (selectMode) return;
    endRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, user, selectMode]);

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
      } ${selectMode ? 'is-select-mode' : ''}`}
    >
      <header className="flex h-[56px] shrink-0 items-center justify-between gap-2 border-b border-[#0e1621] bg-[#17212b] px-2 md:px-3">
        {selectMode ? (
          <>
            <button
              type="button"
              onClick={onExitSelect}
              className="shrink-0 rounded-lg px-3 py-2 text-[13px] text-[#6ab2f2] hover:bg-[#242f3d]"
            >
              انصراف
            </button>
            <span className="fa-num flex-1 truncate text-center text-[15px] font-medium text-white">
              {toPersianDigits(selectedIds.size)} انتخاب
            </span>
            <button
              type="button"
              onClick={onCopySelected}
              disabled={selectedIds.size === 0}
              className="shrink-0 rounded-lg bg-[#242f3d] px-3 py-2 text-[13px] text-[#6ab2f2] hover:bg-[#2b5278] hover:text-white disabled:opacity-40"
            >
              کپی
            </button>
          </>
        ) : (
          <>
            <div className="flex min-w-0 flex-1 items-center gap-2.5">
              <button
                type="button"
                onClick={onBack}
                className={`shrink-0 rounded-full p-2 text-[#6ab2f2] hover:bg-[#242f3d] ${
                  viewerRole === 'user' ? '' : 'md:hidden'
                }`}
                aria-label="بازگشت"
              >
                <IoArrowBack className="h-5 w-5" />
              </button>
              <div
                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white ${getAvatarColor(
                  user,
                )}`}
              >
                {viewerRole === 'user' ? 'پ' : getInitials(user)}
              </div>
              <div className="min-w-0 flex-1">
                <h1 className="support-chat-title truncate text-[16px] font-medium leading-snug text-white">
                  {user}
                </h1>
                <p className="fa-num truncate text-[12px] leading-normal text-[#8b9cb3]">
                  {mobile && mobile !== '-' ? mobile : status || 'پشتیبانی'}
                </p>
              </div>
            </div>
            {showSubscriptions ? (
              <button
                type="button"
                onClick={onOpenSubscriptions}
                className="shrink-0 rounded-lg bg-[#242f3d] px-3 py-2 text-[12px] font-medium text-[#6ab2f2] hover:bg-[#2b5278] hover:text-white"
              >
                اشتراک‌ها
              </button>
            ) : null}
          </>
        )}
      </header>

      {!selectMode ? (
        <PinnedMessagesBar
          scope={pinScope}
          messages={messages}
          pinTick={pinTick}
          onScrollTo={scrollToMessage}
          onUnpin={onUnpin}
        />
      ) : null}

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
            const pinned = isPinned(pinScope, msg.id);
            const isSelected = selectedIds.has(msg.id);
            const bubbleHandlers = selectMode
              ? {
                  onClick: (e: React.MouseEvent<HTMLElement>) => {
                    e.preventDefault();
                    onToggleSelect(msg.id);
                  },
                }
              : getBubbleHandlers(msg, pinned);

            return (
              <div
                key={msg.id}
                ref={(el) => {
                  if (el) messageRefs.current.set(msg.id, el);
                  else messageRefs.current.delete(msg.id);
                }}
              >
                {separator ? (
                  <div className="my-3 flex justify-center">
                    <span className="fa-num rounded-full bg-[#182533] px-3 py-1 text-[11px] text-[#8b9cb3]">
                      {separator}
                    </span>
                  </div>
                ) : null}
                <div
                  className={`msg-row flex w-full items-center gap-2.5 ${
                    msg.is_own ? 'justify-start' : 'justify-end'
                  }`}
                >
                  {selectMode && !msg.is_own ? (
                    <button
                      type="button"
                      className={`msg-select-check shrink-0 ${isSelected ? 'is-checked' : ''}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        onToggleSelect(msg.id);
                      }}
                      aria-label={isSelected ? 'لغو انتخاب' : 'انتخاب پیام'}
                    >
                      {isSelected ? <FiCheck className="h-4 w-4" /> : null}
                    </button>
                  ) : null}
                  <div
                    {...bubbleHandlers}
                    className={`msg-bubble max-w-[min(82%,24rem)] ${selectMode ? '' : 'cursor-pointer'} ${
                      msg.image && !msg.text && !msg.audio ? 'p-1' : 'px-3 py-2'
                    } ${clusterRadius(msg.is_own, pos)} ${
                      msg.is_own ? 'tg-bubble-admin' : 'tg-bubble-user'
                    } ${pinned ? 'is-pinned' : ''} ${isSelected ? 'is-selected' : ''}`}
                  >
                    {msg.reply_to?.text ? (
                      <div className="msg-quote">
                        <strong>{msg.reply_to.sender === 'admin' ? 'پشتیبانی' : 'کاربر'}</strong>
                        <span>{msg.reply_to.text}</span>
                      </div>
                    ) : null}
                    {msg.image ? (
                      <button
                        type="button"
                        className="tg-msg-image-link block overflow-hidden rounded-[10px] border-0 bg-transparent p-0"
                        onClick={(e) => {
                          e.stopPropagation();
                          setLightboxSrc(msg.image);
                        }}
                      >
                        <img
                          src={msg.image}
                          alt=""
                          loading="lazy"
                          draggable={false}
                          className="tg-msg-image block max-h-[360px] w-full max-w-[280px] object-contain"
                        />
                      </button>
                    ) : null}
                    {msg.audio ? (
                      <audio
                        controls
                        preload="metadata"
                        src={msg.audio}
                        className="mb-2 w-full min-w-[200px]"
                        onClick={(e) => e.stopPropagation()}
                      />
                    ) : null}
                    {msg.text ? (
                      <div
                        className={`whitespace-pre-wrap break-words text-[14px] leading-relaxed ${msg.image ? 'mt-2' : ''}`}
                      >
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
                  {selectMode && msg.is_own ? (
                    <button
                      type="button"
                      className={`msg-select-check shrink-0 ${isSelected ? 'is-checked' : ''}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        onToggleSelect(msg.id);
                      }}
                      aria-label={isSelected ? 'لغو انتخاب' : 'انتخاب پیام'}
                    >
                      {isSelected ? <FiCheck className="h-4 w-4" /> : null}
                    </button>
                  ) : null}
                </div>
              </div>
            );
          })}
        </div>
        <div ref={endRef} />
      </div>

      {!selectMode ? (
        <MessageComposer
          ref={composerRef}
          draft={draft}
          sending={sending}
          replyTarget={replyTarget}
          editTarget={editTarget}
          onDraftChange={onDraftChange}
          onClearReply={onClearReply}
          onClearEdit={onClearEdit}
          onSendText={onSendText}
          onSendVoice={onSendVoice}
          onSendImage={onSendImage}
          showVoice={showVoice}
        />
      ) : null}

      {lightboxSrc ? <ImageLightbox src={lightboxSrc} onClose={() => setLightboxSrc(null)} /> : null}

      {menuState ? (
        <MessageContextMenu
          state={menuState}
          onClose={onMenuClose}
          onAction={(action) => onMenuAction(action, menuState.message)}
          viewerRole={viewerRole}
        />
      ) : null}
    </section>
  );
}
