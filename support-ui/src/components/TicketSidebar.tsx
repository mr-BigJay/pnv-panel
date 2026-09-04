import type { Ticket } from '../types';

interface TicketSidebarProps {
  tickets: Ticket[];
  activeUser: string;
  loading: boolean;
  error: string;
  onSelect: (user: string) => void;
  onRefresh: () => void;
}

export function TicketSidebar({
  tickets,
  activeUser,
  loading,
  error,
  onSelect,
  onRefresh,
}: TicketSidebarProps) {
  return (
    <aside className="flex h-full w-full max-w-[360px] shrink-0 flex-col border-l border-tg-border bg-tg-sidebar md:w-[360px]">
      <header className="flex items-center justify-between border-b border-tg-border px-4 py-3">
        <h2 className="text-base font-semibold">پیام‌های کاربران</h2>
        <button
          type="button"
          onClick={onRefresh}
          className="rounded-lg px-2 py-1 text-sm text-tg-accent hover:bg-tg-hover"
          aria-label="بروزرسانی لیست"
        >
          ↻
        </button>
      </header>

      <div className="border-b border-tg-border px-3 py-2">
        <input
          type="search"
          placeholder="جستجو..."
          className="w-full rounded-lg border border-tg-border bg-tg-panel px-3 py-2 text-sm outline-none focus:border-tg-accent"
          disabled
        />
      </div>

      {error ? (
        <div className="px-4 py-3 text-sm text-red-400">{error}</div>
      ) : null}

      <div className="flex-1 overflow-y-auto">
        {loading && tickets.length === 0 ? (
          <div className="px-4 py-8 text-center text-sm text-tg-muted">در حال بارگذاری...</div>
        ) : null}

        {!loading && tickets.length === 0 ? (
          <div className="px-4 py-8 text-center text-sm text-tg-muted">
            هنوز پیامی نیست
          </div>
        ) : null}

        {tickets.map((ticket) => {
          const active = ticket.user === activeUser;
          return (
            <button
              key={ticket.user}
              type="button"
              onClick={() => onSelect(ticket.user)}
              className={`flex w-full items-start gap-3 border-b border-tg-border/50 px-4 py-3 text-right transition hover:bg-tg-hover ${
                active ? 'bg-tg-hover' : ''
              }`}
            >
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-tg-bubbleOwn text-lg font-semibold">
                {ticket.initial}
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <span className="truncate font-medium">{ticket.user}</span>
                  <span className="shrink-0 text-xs text-tg-muted">{ticket.relative_time}</span>
                </div>
                <div className="mt-1 flex items-center justify-between gap-2">
                  <span
                    className={`truncate text-sm ${
                      ticket.unread > 0 ? 'font-semibold text-white' : 'text-tg-muted'
                    }`}
                  >
                    {ticket.preview}
                  </span>
                  {ticket.unread > 0 ? (
                    <span className="shrink-0 rounded-full bg-tg-accent px-2 py-0.5 text-xs font-bold text-tg-bg">
                      {ticket.unread > 9 ? '9+' : ticket.unread}
                    </span>
                  ) : null}
                </div>
              </div>
            </button>
          );
        })}
      </div>
    </aside>
  );
}
