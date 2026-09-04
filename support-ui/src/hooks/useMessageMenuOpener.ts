import { useCallback, useEffect, useRef, useState } from 'react';
import type { SupportMessage } from '../types';
import type { MessageContextMenuState } from '../components/MessageContextMenu';

const HOLD_MS = 420;

export function useMessageMenuOpener(onOpen: (state: MessageContextMenuState) => void) {
  const holdTimer = useRef<number | null>(null);
  const holdStart = useRef<{ x: number; y: number } | null>(null);
  const holdMessage = useRef<SupportMessage | null>(null);
  const holdBubble = useRef<HTMLElement | null>(null);
  const menuOpenedFromHold = useRef(false);

  const clearHold = useCallback(() => {
    if (holdTimer.current !== null) {
      window.clearTimeout(holdTimer.current);
      holdTimer.current = null;
    }
    if (holdBubble.current) {
      holdBubble.current.classList.remove('is-holding');
    }
    holdStart.current = null;
    holdMessage.current = null;
    holdBubble.current = null;
  }, []);

  const openForBubble = useCallback(
    (message: SupportMessage, bubble: HTMLElement, pinned: boolean) => {
      try {
        window.getSelection()?.removeAllRanges();
      } catch {
        /* ignore */
      }
      try {
        navigator.vibrate?.(18);
      } catch {
        /* ignore */
      }
      onOpen({
        message,
        anchorRect: bubble.getBoundingClientRect(),
        pinned,
      });
    },
    [onOpen],
  );

  const blockNativeSelect = useCallback((e: React.SyntheticEvent) => {
    e.preventDefault();
  }, []);

  const getBubbleHandlers = useCallback(
    (message: SupportMessage, pinned: boolean) => ({
      onContextMenu: (e: React.MouseEvent<HTMLElement>) => {
        if ((e.target as HTMLElement).closest('button,textarea,input,a,audio')) return;
        e.preventDefault();
        openForBubble(message, e.currentTarget, pinned);
      },
      onTouchStart: (e: React.TouchEvent<HTMLElement>) => {
        if ((e.target as HTMLElement).closest('button,textarea,input,a,audio')) return;
        clearHold();
        const touch = e.touches[0];
        holdStart.current = touch ? { x: touch.clientX, y: touch.clientY } : null;
        holdMessage.current = message;
        holdBubble.current = e.currentTarget;
        e.currentTarget.classList.add('is-holding');
        holdTimer.current = window.setTimeout(() => {
          const target = holdBubble.current;
          const msg = holdMessage.current;
          menuOpenedFromHold.current = true;
          clearHold();
          if (target && msg) openForBubble(msg, target, pinned);
        }, HOLD_MS);
      },
      onTouchMove: (e: React.TouchEvent<HTMLElement>) => {
        if (!holdStart.current || !e.touches[0]) return;
        const dx = e.touches[0].clientX - holdStart.current.x;
        const dy = e.touches[0].clientY - holdStart.current.y;
        if (dx * dx + dy * dy > 100) clearHold();
      },
      onTouchEnd: (e: React.TouchEvent<HTMLElement>) => {
        if (menuOpenedFromHold.current) {
          e.preventDefault();
          menuOpenedFromHold.current = false;
          try {
            window.getSelection()?.removeAllRanges();
          } catch {
            /* ignore */
          }
        }
        clearHold();
      },
      onTouchCancel: () => {
        menuOpenedFromHold.current = false;
        clearHold();
      },
      onSelectStart: blockNativeSelect,
      onDragStart: blockNativeSelect,
    }),
    [blockNativeSelect, clearHold, openForBubble],
  );

  useEffect(() => () => clearHold(), [clearHold]);

  return { getBubbleHandlers, clearHold };
}

export function usePinnedRefresh(pinScope: string) {
  const [pinTick, setPinTick] = useState(0);
  const bump = useCallback(() => setPinTick((n) => n + 1), []);
  return { pinScope, pinTick, bumpPins: bump };
}
