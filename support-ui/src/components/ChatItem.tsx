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
  const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  const parts = text.split(regex);
  return parts.map((part, index) =>
    regex.test(part) ? (
      <mark key={index} className="rounded bg-yellow-800 px-0.5">
        {part}
      </mark>
    ) : (
      part
    ),
  );
}

export function ChatItem({ ticket, active, searchQuery, onClick }: ChatItemProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex w-full items-center gap-3 p-3 text-right transition-colors hover:bg-[#151515] ${
        active ? 'bg-[#151515]' : ''
      }`}
    >
      <div
        className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white ${getAvatarColor(
          ticket.user,
        )}`}
      >
        {ticket.initial || getInitials(ticket.user)}
      </div>

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <h3 className="truncate font-medium text-white">
            {searchQuery ? highlightText(ticket.user, searchQuery) : ticket.user}
          </h3>
          <span className="fa-num mr-auto shrink-0 text-xs text-gray-500">
            {toPersianDigits(ticket.relative_time)}
          </span>
        </div>
        <div className="mt-0.5 flex items-center gap-2">
          <p
            className={`truncate text-sm text-gray-500 ${
              ticket.unread > 0 ? 'font-semibold text-gray-300' : ''
            }`}
          >
            {searchQuery ? highlightText(ticket.preview, searchQuery) : ticket.preview}
          </p>
          {ticket.unread > 0 ? (
            <span className="mr-auto flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-blue-500 px-1.5 text-xs font-medium text-white">
              {ticket.unread > 9 ? '9+' : ticket.unread}
            </span>
          ) : null}
        </div>
      </div>
    </button>
  );
}
