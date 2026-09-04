import { useLayoutEffect, useRef } from 'react';
import {
  FiCheckCircle,
  FiCopy,
  FiCornerUpLeft,
  FiEdit2,
  FiMapPin,
  FiTrash2,
} from 'react-icons/fi';
import type { SupportMessage } from '../types';

export type MessageMenuAction =
  | 'reply'
  | 'edit'
  | 'pin'
  | 'copy'
  | 'delete'
  | 'select';

export interface MessageContextMenuState {
  message: SupportMessage;
  anchorRect: DOMRect;
  pinned: boolean;
}

interface MessageContextMenuProps {
  state: MessageContextMenuState;
  onAction: (action: MessageMenuAction) => void;
  onClose: () => void;
}

interface MenuItem {
  key: MessageMenuAction;
  label: string;
  icon: React.ReactNode;
  danger?: boolean;
  hidden?: boolean;
}

function positionMenu(menu: HTMLElement, anchorRect: DOMRect) {
  const menuRect = menu.getBoundingClientRect();
  let top = anchorRect.top + anchorRect.height / 2 - menuRect.height / 2;
  let left = anchorRect.left - menuRect.width - 12;

  if (left < 8) {
    left = anchorRect.right + 12;
  }

  if (left + menuRect.width > window.innerWidth - 8) {
    left = Math.max(8, (window.innerWidth - menuRect.width) / 2);
  }

  top = Math.max(8, Math.min(top, window.innerHeight - menuRect.height - 8));

  return { top, left };
}

export function MessageContextMenu({ state, onAction, onClose }: MessageContextMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null);
  const { message, anchorRect, pinned } = state;
  const hasText = Boolean(message.text?.trim());

  const items: MenuItem[] = [
    {
      key: 'reply' as const,
      label: 'Reply',
      icon: <FiCornerUpLeft className="h-[18px] w-[18px]" />,
      hidden: message.is_own,
    },
    {
      key: 'edit' as const,
      label: 'Edit',
      icon: <FiEdit2 className="h-[18px] w-[18px]" />,
    },
    {
      key: 'pin' as const,
      label: pinned ? 'Unpin' : 'Pin',
      icon: <FiMapPin className="h-[18px] w-[18px]" />,
    },
    {
      key: 'copy' as const,
      label: 'Copy Text',
      icon: <FiCopy className="h-[18px] w-[18px]" />,
      hidden: !hasText,
    },
    {
      key: 'delete' as const,
      label: 'Delete',
      icon: <FiTrash2 className="h-[18px] w-[18px]" />,
      danger: true,
    },
    {
      key: 'select' as const,
      label: 'Select',
      icon: <FiCheckCircle className="h-[18px] w-[18px]" />,
    },
  ].filter((item) => !item.hidden);

  useLayoutEffect(() => {
    const menu = menuRef.current;
    if (!menu) return;
    const { top, left } = positionMenu(menu, anchorRect);
    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
  }, [anchorRect]);

  useLayoutEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="support-message-overlay" onClick={onClose} role="presentation">
      <div
        ref={menuRef}
        className="support-context-menu"
        role="menu"
        onClick={(e) => e.stopPropagation()}
      >
        {items.map((item) => (
          <button
            key={item.key}
            type="button"
            role="menuitem"
            className={item.danger ? 'danger' : undefined}
            onClick={() => onAction(item.key)}
          >
            <span className="support-context-menu-icon">{item.icon}</span>
            <span>{item.label}</span>
          </button>
        ))}
      </div>
    </div>
  );
}
