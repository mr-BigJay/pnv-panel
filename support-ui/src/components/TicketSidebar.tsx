import { useMemo, useState } from 'react';
import type { Ticket } from '../types';
import { ChatItem } from './ChatItem';
import { SearchInput } from './SearchInput';

interface TicketSidebarProps {
  tickets: Ticket[];
  activeUser: string;
  loading: boolean;
  error: string;
  onSelect: (user: string) => void;
  onRefresh: () => void;
  mobileVisible: boolean;
}

function filterTickets(tickets: Ticket[], query: string): Ticket[] {
  const q = query.trim().toLowerCase();
  if (!q) return tickets;
  return tickets.filter(
    (t) => t.user.toLowerCase().includes(q) || t.preview.toLowerCase().includes(q),
  );
}

export function TicketSidebar({
  tickets,
  activeUser,
  loading,
  error,
  onSelect,
  onRefresh,
  mobileVisible,
}: TicketSidebarProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const filtered = useMemo(() => filterTickets(tickets, searchQuery), [tickets, searchQuery]);

  return (
    <aside
      className={`flex h-full w-full shrink-0 flex-col border-l border-[#2d2d2d] bg-[#1a1a1a] md:w-96 ${
        mobileVisible ? 'flex' : 'hidden md:flex'
      }`}
    >
      <header className="flex items-center gap-2 border-b border-[#2d2d2d] p-3">
        <button
          type="button"
          onClick={onRefresh}
          className="rounded-full p-2 text-gray-400 transition hover:bg-[#151515] hover:text-white"
          title="بروزرسانی"
        >
          ↻
        </button>
        <SearchInput onSearch={setSearchQuery} placeholder="جستجو در چت‌ها..." />
      </header>

      {error ? <div className="px-4 py-3 text-sm text-red-400">{error}</div> : null}

      <div className="flex-1 overflow-y-auto tg-scroll">
        {loading && tickets.length === 0 ? (
          <div className="flex h-40 items-center justify-center text-sm text-gray-500">
            در حال بارگذاری...
          </div>
        ) : null}

        {!loading && filtered.length === 0 ? (
          <div className="flex h-64 flex-col items-center justify-center px-6 text-center text-gray-500">
            <div className="mb-3 text-4xl">{searchQuery ? '🔍' : '📭'}</div>
            <p className="font-medium">{searchQuery ? 'چتی پیدا نشد' : 'هنوز پیامی نیست'}</p>
          </div>
        ) : null}

        {filtered.map((ticket) => (
          <ChatItem
            key={ticket.user}
            ticket={ticket}
            active={ticket.user === activeUser}
            searchQuery={searchQuery}
            onClick={() => onSelect(ticket.user)}
          />
        ))}
      </div>
    </aside>
  );
}
