import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createSupportApi, type UserSupportApi } from './api/client';
import { ChatPanel, type ChatPanelHandle } from './components/ChatPanel';
import type { MessageContextMenuState, MessageMenuAction } from './components/MessageContextMenu';
import { usePinnedRefresh } from './hooks/useMessageMenuOpener';
import { togglePin, unpinMessage } from './lib/messagePins';
import type { MessageComposerHandle } from './components/MessageComposer';
import { getSupportConfig, type ReplyTarget, type SupportMessage } from './types';

export function UserApp() {
  const config = useMemo(() => getSupportConfig(), []);
  const api = useMemo(() => createSupportApi(config) as UserSupportApi, [config]);

  const [csrf, setCsrf] = useState(config.csrf);
  const [messages, setMessages] = useState<SupportMessage[]>([]);
  const [status, setStatus] = useState('');
  const [messagesLoading, setMessagesLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [chatError, setChatError] = useState('');
  const [draft, setDraft] = useState('');
  const [menuState, setMenuState] = useState<MessageContextMenuState | null>(null);
  const [replyTarget, setReplyTarget] = useState<ReplyTarget | null>(null);
  const [editTarget, setEditTarget] = useState<ReplyTarget | null>(null);
  const [selectMode, setSelectMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());

  const sinceRef = useRef(0);
  const composerRef = useRef<MessageComposerHandle | null>(null);
  const chatPanelRef = useRef<ChatPanelHandle | null>(null);
  const pollMs = config.pollIntervalMs;
  const pinScope = config.pinScope || 'user';
  const { pinTick, bumpPins } = usePinnedRefresh(pinScope);

  const chatTitle = config.displayTitle || 'پشتیبانی';
  const chatSubtitle = config.displaySubtitle || status || 'پشتیبانی';

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const boot = await api.bootstrap();
        if (!cancelled) setCsrf(boot.csrf);
      } catch {
        /* keep config csrf */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [api]);

  const loadMessages = useCallback(
    async (initial = false) => {
      setChatError('');
      if (initial) {
        setMessagesLoading(true);
        sinceRef.current = 0;
      }

      try {
        const data = await api.messages(sinceRef.current, false);
        setStatus(data.status);

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
    [api],
  );

  useEffect(() => {
    loadMessages(true);
  }, [loadMessages]);

  useEffect(() => {
    const timer = window.setInterval(() => {
      loadMessages(false);
    }, pollMs);
    return () => window.clearInterval(timer);
  }, [loadMessages, pollMs]);

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
    const trimmed = text.trim();
    if (!trimmed) return;

    setSending(true);
    setChatError('');

    try {
      if (editTarget) {
        const res = await api.edit(editTarget.id, trimmed, csrf);
        if (res.message) mergeMessage(res.message);
        setEditTarget(null);
        setDraft('');
      } else {
        const res = await api.send(trimmed, csrf, replyTarget?.id ?? '');
        if (res.message) mergeMessage(res.message);
        setReplyTarget(null);
        setDraft('');
      }
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال ناموفق');
    } finally {
      setSending(false);
    }
  }

  async function handleSendVoice(blob: Blob) {
    setSending(true);
    setChatError('');
    try {
      const res = await api.sendVoice(blob, csrf);
      if (res.message) mergeMessage(res.message);
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال ویس ناموفق');
    } finally {
      setSending(false);
    }
  }

  async function handleSendImage(file: File, caption = '') {
    setSending(true);
    setChatError('');
    try {
      const res = await api.sendImage(file, csrf, caption, replyTarget?.id ?? '');
      if (res.message) mergeMessage(res.message);
      setReplyTarget(null);
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال تصویر ناموفق');
    } finally {
      setSending(false);
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
          const res = await api.deleteMessage(message.id, csrf);
          if (!res.ok) {
            alert(res.error || 'حذف ناموفق بود');
            return;
          }
          setMessages((prev) => prev.filter((m) => m.id !== message.id));
        } catch (err) {
          alert(err instanceof Error ? err.message : 'خطا در حذف پیام');
        } finally {
          setSending(false);
        }
      }
    },
    [api, bumpPins, csrf, pinScope],
  );

  function handleBack() {
    window.location.href = config.backUrl || 'dashboard.php';
  }

  return (
    <div className="support-v2-shell flex h-full overflow-hidden bg-[#0e1621] text-[#e4ecf4]" dir="rtl">
      <ChatPanel
        ref={chatPanelRef}
        user={chatTitle}
        mobile={chatSubtitle}
        messages={messages}
        status={status}
        loading={messagesLoading}
        sending={sending}
        error={chatError}
        draft={draft}
        mobileVisible={false}
        pinScope={pinScope}
        pinTick={pinTick}
        replyTarget={replyTarget}
        editTarget={editTarget}
        menuState={menuState}
        selectMode={selectMode}
        selectedIds={selectedIds}
        composerRef={composerRef}
        viewerRole="user"
        showSubscriptions={false}
        showVoice={true}
        onDraftChange={setDraft}
        onClearReply={() => setReplyTarget(null)}
        onClearEdit={() => {
          setEditTarget(null);
          setDraft('');
        }}
        onBack={handleBack}
        onOpenSubscriptions={() => {}}
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
    </div>
  );
}
