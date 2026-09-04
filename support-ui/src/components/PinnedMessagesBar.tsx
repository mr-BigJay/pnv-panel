import { FiMapPin, FiX } from 'react-icons/fi';
import { loadPinnedIds, pinPreview } from '../lib/messagePins';
import type { SupportMessage } from '../types';

interface PinnedMessagesBarProps {
  scope: string;
  messages: SupportMessage[];
  pinTick: number;
  onScrollTo: (id: string) => void;
  onUnpin: (id: string) => void;
}

export function PinnedMessagesBar({
  scope,
  messages,
  pinTick,
  onScrollTo,
  onUnpin,
}: PinnedMessagesBarProps) {
  void pinTick;
  const pinnedIds = loadPinnedIds(scope);
  if (!pinnedIds.length) return null;

  const byId = new Map(messages.map((m) => [m.id, m]));
  const pinned = pinnedIds.map((id) => byId.get(id)).filter(Boolean) as SupportMessage[];
  if (!pinned.length) return null;

  return (
    <div className="support-pinned-bar shrink-0">
      {pinned.map((msg) => (
        <div key={msg.id} className="support-pinned-item">
          <button
            type="button"
            className="support-pinned-body"
            onClick={() => onScrollTo(msg.id)}
            title={pinPreview(msg)}
          >
            <FiMapPin className="support-pinned-icon shrink-0" aria-hidden="true" />
            <span className="support-pinned-text">{pinPreview(msg)}</span>
          </button>
          <button
            type="button"
            className="support-pinned-unpin"
            onClick={() => onUnpin(msg.id)}
            aria-label="برداشتن سنجاق"
          >
            <FiX className="h-4 w-4" />
          </button>
        </div>
      ))}
    </div>
  );
}
