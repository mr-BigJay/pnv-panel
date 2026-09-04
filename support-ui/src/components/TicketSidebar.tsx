import { useMemo, useState } from 'react';
import { FiRefreshCw } from 'react-icons/fi';
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
    (t) =>
      t.user.toLowerCase().includes(q) ||
      t.preview.toLowerCase().includes(q) ||
      (t.mobile || '').toLowerCase().includes(q),
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
      className={`tg-sidebar flex h-full w-full shrink-0 flex-col border-l md:w-[340px] lg:w-[380px] ${
        mobileVisible ? 'flex' : 'hidden md:flex'
      }`}
    >
      <header className="shrink-0 border-b border-[#0e1621] px-3 py-3">
        <div className="mb-3 flex items-center justify-between gap-2">
          <h2 className="support-chat-item-name text-[15px] font-medium leading-snug text-white">
            پیام‌های کاربران
          </h2>
          <button
            type="button"
            onClick={onRefresh}
            className="rounded-full p-2 text-[#6d8399] transition hover:bg-[#242f3d] hover:text-[#6ab2f2]"
            title="بروزرسانی"
          >
            <FiRefreshCw className={`h-[18px] w-[18px] ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
        <SearchInput onSearch={setSearchQuery} placeholder="جستجو در چت‌ها..." />
      </header>

      {error ? <div className="px-4 py-3 text-sm text-red-400">{error}</div> : null}

      <div className="min-h-0 flex-1 overflow-y-auto tg-scroll">
        {loading && tickets.length === 0 ? (
          <div className="flex h-40 items-center justify-center text-sm text-[#6d8399]">
            در حال بارگذاری...
          </div>
        ) : null}

        {!loading && filtered.length === 0 ? (
          <div className="flex h-64 flex-col items-center justify-center px-6 text-center text-[#6d8399]">
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
