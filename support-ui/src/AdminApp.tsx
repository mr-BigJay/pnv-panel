import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createSupportApi } from './api/client';
import { ChatPanel } from './components/ChatPanel';
import { TicketSidebar } from './components/TicketSidebar';
import { getSupportConfig, type SupportMessage, type Ticket } from './types';

export function AdminApp() {
  const config = useMemo(() => getSupportConfig(), []);
  const api = useMemo(() => createSupportApi(config), [config]);

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

  const sinceRef = useRef(0);
  const pollMs = config.pollIntervalMs;

  const showSidebar = isMobile ? !activeUser : true;
  const showChat = isMobile ? !!activeUser : true;

  useEffect(() => {
    const check = () => setIsMobile(window.innerWidth < 768);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

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

  async function handleSend(text: string) {
    if (!activeUser) return;
    setSending(true);
    setChatError('');
    try {
      const res = await api.send(activeUser, text, csrf);
      if (res.message) {
        setMessages((prev) => {
          if (prev.some((m) => m.id === res.message!.id)) return prev;
          return [...prev, res.message!].sort((a, b) => a.timestamp - b.timestamp);
        });
        if (res.message.timestamp > sinceRef.current) {
          sinceRef.current = res.message.timestamp;
        }
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
        setMessages((prev) => {
          if (prev.some((m) => m.id === res.message!.id)) return prev;
          return [...prev, res.message!].sort((a, b) => a.timestamp - b.timestamp);
        });
        if (res.message.timestamp > sinceRef.current) {
          sinceRef.current = res.message.timestamp;
        }
      }
      await loadTickets();
    } catch (err) {
      setChatError(err instanceof Error ? err.message : 'ارسال ویس ناموفق');
    } finally {
      setSending(false);
    }
  }

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
          user={activeUser}
          mobile={activeMobile}
          messages={messages}
          status={status}
          loading={messagesLoading}
          sending={sending}
          error={chatError}
          draft={draft}
          mobileVisible={!showChat}
          onDraftChange={setDraft}
          onBack={() => setActiveUser('')}
          onSendText={async () => {
            const text = draft.trim();
            if (!text) return;
            setDraft('');
            await handleSend(text);
          }}
          onSendVoice={handleSendVoice}
        />
      ) : null}
    </div>
  );
}
