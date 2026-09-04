import type { Ticket } from '../types';
import { getAvatarColor, getInitials } from '../lib/avatarUtils';
import { toPersianDigits } from '../lib/persianDigits';

interface ChatItemProps {
  ticket: Ticket;
  active: boolean;
  searchQuery?: string;
  onClick: () => void;
}

function highlightText(text: string, query?: string) {
  if (!query?.trim()) return text;
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`(${escaped})`, 'gi');
  const parts = text.split(regex);
  const lowerQuery = query.trim().toLowerCase();

  return parts.map((part, index) =>
    part.toLowerCase() === lowerQuery ? (
      <mark key={index} className="rounded bg-[#6ab2f2]/30 px-0.5 text-inherit">
        {part}
      </mark>
    ) : (
      part
    ),
  );
}

function ticketListTime(ticket: Ticket): string {
  const raw = ticket.list_time || ticket.relative_time || '';
  return toPersianDigits(raw);
}

export function ChatItem({ ticket, active, searchQuery, onClick }: ChatItemProps) {
  return (
    <div
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
      className={`support-chat-item flex w-full cursor-pointer items-center gap-3 border-b border-[#0e1621]/80 px-3 py-2.5 text-right transition-colors ${
        active ? 'bg-[#2b5278]' : 'hover:bg-[#202b36]'
      }`}
    >
      <div
        className={`flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-full text-sm font-medium text-white ${getAvatarColor(
          ticket.user,
        )}`}
      >
        {ticket.initial || getInitials(ticket.user)}
      </div>

      <div className="min-w-0 flex-1">
        <div className="grid grid-cols-[minmax(0,1fr)_5.25rem] items-center gap-2">
          <span className="support-chat-item-name min-w-0 truncate text-[15px] font-medium leading-snug text-white">
            {searchQuery ? highlightText(ticket.user, searchQuery) : ticket.user}
          </span>
          <span className="support-chat-item-time fa-num truncate text-left text-[12px] leading-none text-[#6d8399]">
            {ticketListTime(ticket)}
          </span>
        </div>
        <div className="mt-1 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
          <p
            className={`min-w-0 truncate text-[13px] leading-snug ${
              ticket.unread > 0 ? 'font-medium text-[#e4ecf4]' : 'text-[#8b9cb3]'
            }`}
          >
            {searchQuery ? highlightText(ticket.preview, searchQuery) : ticket.preview}
          </p>
          {ticket.unread > 0 ? (
            <span className="fa-num flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[#6ab2f2] px-1.5 text-[11px] font-bold text-white">
              {ticket.unread > 9 ? '9+' : ticket.unread}
            </span>
          ) : (
            <span aria-hidden="true" className="h-5 w-0" />
          )}
        </div>
      </div>
    </div>
  );
}
