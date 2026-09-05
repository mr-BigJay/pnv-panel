import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createAdminSupportApi } from './api/client';
import { ChatPanel, type ChatPanelHandle } from './components/ChatPanel';
import { TicketSidebar } from './components/TicketSidebar';
import { UserSubscriptionsModal } from './components/UserSubscriptionsModal';
import type { MessageContextMenuState, MessageMenuAction } from './components/MessageContextMenu';
import { usePinnedRefresh } from './hooks/useMessageMenuOpener';
import { togglePin, unpinMessage } from './lib/messagePins';
import type { MessageComposerHandle } from './components/MessageComposer';
import { getSupportConfig, type ReplyTarget, type SupportMessage, type Ticket } from './types';

export function AdminApp() {
  const config = useMemo(() => getSupportConfig(), []);
  const api = useMemo(() => createAdminSupportApi(config), [config]);

  const [csrf, setCsrf] = useState(config.csrf);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [activeUser, setActiveUser] = useState(config.initialUser);
  const [activeMobile, setActiveMobile] = useState('-');
  const [messages, setMessages] = useState<SupportMessage[]>([]);
  const [status, setStatus] = useState('');
  const [ticketsLoading, setTicketsLoading] = useState(true);
  const [messagesLoading, setMessagesLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [sidebarError, setSidebarError] = useState('');
  const [chatError, setChatError] = useState('');
  const [draft, setDraft] = useState('');
  const [isMobile, setIsMobile] = useState(false);
  const [showSubscriptions, setShowSubscriptions] = useState(false);
  const [menuState, setMenuState] = useState<MessageContextMenuState | null>(null);
  const [replyTarget, setReplyTarget] = useState<ReplyTarget | null>(null);
  const [editTarget, setEditTarget] = useState<ReplyTarget | null>(null);
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());

  const sinceRef = useRef(0);
  const composerRef = useRef<MessageComposerHandle | null>(null);
  const chatPanelRef = useRef<ChatPanelHandle | null>(null);
  const pollMs = config.pollIntervalMs;
  const pinScope = activeUser || 'default';
  const { pinTick, bumpPins } = usePinnedRefresh(pinScope);

  const showSidebar = isMobile ? !activeUser : true;
  const showChat = isMobile ? !!activeUser : true;

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 768);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

  useEffect(() => {
    setReplyTarget(null);
    setEditTarget(null);
    setDraft('');
    setMenuState(null);
    setSelectMode(false);
    setSelectedIds(new Set());
  }, [activeUser]);

  const loadTickets = useCallback(async () => {
    setSidebarError('');
    setTicketsLoading(true);
    try {
      const data = await api.tickets();
      setTickets(data.tickets);
    } catch (err) {
      setSidebarError(err instanceof Error ? err.message : 'خطا در بارگذاری لیست');
    } finally {
      setTicketsLoading(false);
    }
  }, [api]);

  const loadMessages = useCallback(
    async (user: string, initial = false) => {
      if (!user) {
        setMessages([]);
        setStatus('');
        sinceRef.current = 0;
        return;
      }

      setChatError('');
      if (initial) {
        setMessagesLoading(true);
        sinceRef.current = 0;
      }

      try {
        const data = await api.messages(user, sinceRef.current, false);
        setStatus(data.status);
        const ticket = tickets.find((t) => t.user === user);
        if (ticket?.mobile) setActiveMobile(ticket.mobile);

        if (data.messages.length > 0) {
          setMessages((prev) => {
            const merged = initial ? [] : [...prev];
            const ids = new Set(merged.map((m) => m.id));
            data.messages.forEach((m) => {
              if (!ids.has(m.id)) merged.push(m);
            });
            merged.sort((a, b) => a.timestamp - b.timestamp);
            return merged;
          });
          const lastTs = Math.max(...data.messages.map((m) => m.timestamp));
          if (lastTs > sinceRef.current) sinceRef.current = lastTs;
        } else if (initial) {
          setMessages([]);
        }
      } catch (err) {
        setChatError(err instanceof Error ? err.message : 'خطا در بارگذاری پیام‌ها');
      } finally {
        if (initial) setMessagesLoading(false);
      }
    },
    [api, tickets],
  );

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const boot = await api.bootstrap();
        if (!cancelled) setCsrf(boot.csrf);
      } catch {
        /* keep config csrf */
      }
      if (!cancelled) await loadTickets();
    })();
    return () => {
      cancelled = true;
    };
  }, [api, loadTickets]);

  useEffect(() => {
    loadMessages(activeUser, true);
  }, [activeUser, loadMessages]);

  useEffect(() => {
    if (!activeUser) return undefined;
    const timer = window.setInterval(() => {
      loadMessages(activeUser, false);
      loadTickets();
    }, pollMs);
    return () => window.clearInterval(timer);
  }, [activeUser, loadMessages, loadTickets, pollMs]);

  useEffect(() => {
    const ticket = tickets.find((t) => t.user === activeUser);
    if (ticket?.mobile) setActiveMobile(ticket.mobile);
  }, [tickets, activeUser]);

  const scrollChatToEnd = useCallback(() => {
    chatPanelRef.current?.scrollToEnd();
  }, []);

  const mergeMessage = useCallback(
    (message: SupportMessage) => {
      setMessages((prev) => {
        const idx = prev.findIndex((m) => m.id === message.id);
        if (idx >= 0) {
          const next = [...prev];
          next[idx] = message;
          return next;
        }
        return [...prev, message].sort((a, b) => a.timestamp - b.timestamp);
      });
      if (message.timestamp > sinceRef.current) {
        sinceRef.current = message.timestamp;
      }
      scrollChatToEnd();
    },
    [scrollChatToEnd],
  );

  async function handleSendText(text: string) {
    if (!activeUser) return;
    const trimmed = text.trim();
    if (!trimmed) return;

    setSending(true);
    setChatError('');

    try {
      if (editTarget) {
        const res = await api.edit(activeUser, editTarget.id, trimmed, csrf);
        if (res.message) {
          mergeMessage(res.message);
        }
        setEditTarget(null);
        setDraft('');
      } else {
        const res = await api.send(activeUser, trimmed, csrf, replyTarget?.id ?? '');
        if (res.message) {
          mergeMessage(res.message);
        }
        setReplyTarget(null);
        setDraft('');
      }
      await loadTickets();
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال ناموفق');
    } finally {
      setSending(false);
    }
  }

  async function handleSendVoice(blob: Blob) {
    if (!activeUser) return;
    setSending(true);
    setChatError('');
    try {
      const res = await api.sendVoice(activeUser, blob, csrf);
      if (res.message) {
        mergeMessage(res.message);
      }
      await loadTickets();
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال ویس ناموفق');
    } finally {
      setSending(false);
    }
  }

  async function handleSendImage(file: File, caption = '') {
    if (!activeUser) return;
    setSending(true);
    setChatError('');
    try {
      const res = await api.sendImage(activeUser, file, csrf, caption, replyTarget?.id ?? '');
      if (res.message) {
        mergeMessage(res.message);
      }
      setReplyTarget(null);
      await loadTickets();
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال تصویر ناموفق');
    } finally {
      setSending(false);
      scrollChatToEnd();
    }
  }

  const handleMenuOpen = useCallback((state: MessageContextMenuState) => {
    setMenuState(state);
  }, []);

  const handleMenuClose = useCallback(() => {
    setMenuState(null);
  }, []);

  const handleExitSelect = useCallback(() => {
    setSelectMode(false);
    setSelectedIds(new Set());
  }, []);

  const handleToggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const handleCopySelected = useCallback(() => {
    const texts = messages
      .filter((m) => selectedIds.has(m.id) && m.text?.trim())
      .map((m) => m.text.trim());
    if (!texts.length) {
      alert('پیامی انتخاب نشده');
      return;
    }
    navigator.clipboard
      .writeText(texts.join('\n'))
      .then(() => handleExitSelect())
      .catch(() => alert('کپی ناموفق بود'));
  }, [messages, selectedIds, handleExitSelect]);

  const handleUnpin = useCallback(
    (messageId: string) => {
      unpinMessage(pinScope, messageId);
      bumpPins();
    },
    [pinScope, bumpPins],
  );

  const handleMenuAction = useCallback(
    async (action: MessageMenuAction, message: SupportMessage) => {
      setMenuState(null);

      if (action === 'reply') {
        setEditTarget(null);
        setReplyTarget({
          id: message.id,
          text: message.text || 'پیام',
          sender: message.sender,
        });
        window.setTimeout(() => composerRef.current?.focus(), 0);
        return;
      }

      if (action === 'edit') {
        setReplyTarget(null);
        setEditTarget({
          id: message.id,
          text: message.text || 'پیام',
          sender: message.sender,
        });
        setDraft(message.text || '');
        window.setTimeout(() => composerRef.current?.focus(), 0);
        return;
      }

      if (action === 'pin') {
        togglePin(pinScope, message.id);
        bumpPins();
        return;
      }

      if (action === 'copy') {
        const copyText = message.text?.trim() || '';
        if (!copyText) return;
        try {
          await navigator.clipboard.writeText(copyText);
          try {
            navigator.vibrate?.(8);
          } catch {
            /* ignore */
          }
        } catch {
          alert('کپی ناموفق بود');
        }
        return;
      }

      if (action === 'select') {
        setSelectMode(true);
        setSelectedIds(new Set([message.id]));
        return;
      }

      if (action === 'delete') {
        if (!window.confirm('پیام حذف شود؟')) return;
        setSending(true);
        setChatError('');
        try {
          const res = await api.deleteMessage(activeUser, message.id, csrf);
          if (!res.ok) {
            alert(res.error || 'حذف ناموفق بود');
            return;
          }
          setMessages((prev) => prev.filter((m) => m.id !== message.id));
          await loadTickets();
        } catch (err) {
          alert(err instanceof Error ? err.message : 'خطا در حذف پیام');
        } finally {
          setSending(false);
        }
      }
    },
    [activeUser, api, bumpPins, csrf, loadTickets, pinScope],
  );

  return (
    <div
      className={`support-v2-shell flex h-full overflow-hidden bg-[#0e1621] text-[#e4ecf4] ${
        config.embedded ? 'support-v2-embedded' : ''
      }`}
      dir="rtl"
    >
      <TicketSidebar
        tickets={tickets}
        activeUser={activeUser}
        loading={ticketsLoading}
        error={sidebarError}
        onSelect={setActiveUser}
        onRefresh={loadTickets}
        mobileVisible={showSidebar}
      />
      {showChat ? (
        <ChatPanel
          ref={chatPanelRef}
          user={activeUser}
          mobile={activeMobile}
          messages={messages}
          status={status}
          loading={messagesLoading}
          sending={sending}
          error={chatError}
          draft={draft}
          mobileVisible={!showChat}
          pinScope={pinScope}
          pinTick={pinTick}
          replyTarget={replyTarget}
          editTarget={editTarget}
          menuState={menuState}
          selectMode={selectMode}
          selectedIds={selectedIds}
          composerRef={composerRef}
          onDraftChange={setDraft}
          onClearReply={() => setReplyTarget(null)}
          onClearEdit={() => {
            setEditTarget(null);
            setDraft('');
          }}
          onBack={() => setActiveUser('')}
          onOpenSubscriptions={() => setShowSubscriptions(true)}
          onSendText={handleSendText}
          onSendVoice={handleSendVoice}
          onSendImage={handleSendImage}
          onMenuOpen={handleMenuOpen}
          onMenuClose={handleMenuClose}
          onMenuAction={handleMenuAction}
          onToggleSelect={handleToggleSelect}
          onExitSelect={handleExitSelect}
          onCopySelected={handleCopySelected}
          onUnpin={handleUnpin}
        />
      ) : null}
      {showSubscriptions && activeUser ? (
        <UserSubscriptionsModal
          user={activeUser}
          profileApiUrl={config.profileApiUrl}
          onClose={() => setShowSubscriptions(false)}
        />
      ) : null}
    </div>
  );
}
