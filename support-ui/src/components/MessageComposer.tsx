import { FormEvent, KeyboardEvent, useRef } from 'react';
import { FaPaperclip } from 'react-icons/fa';
import { FaMicrophone } from 'react-icons/fa6';
import { IoSend } from 'react-icons/io5';
import { useVoiceRecorder } from '../hooks/useVoiceRecorder';
import { toPersianDigits } from '../lib/persianDigits';

interface MessageComposerProps {
  draft: string;
  sending: boolean;
  onDraftChange: (value: string) => void;
  onSendText: (text: string) => Promise<void>;
  onSendVoice: (blob: Blob) => Promise<void>;
}

export function MessageComposer({
  draft,
  sending,
  onDraftChange,
  onSendText,
  onSendVoice,
}: MessageComposerProps) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const { recording, seconds, start, stop, cancel } = useVoiceRecorder();
  const hasText = draft.trim().length > 0;

  function growTextarea(el: HTMLTextAreaElement | null) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
  }

  async function submit(e?: FormEvent) {
    e?.preventDefault();
    if (recording) return;
    const text = draft.trim();
    if (!text || sending) return;
    onDraftChange('');
    growTextarea(textareaRef.current);
    await onSendText(text);
  }

  function onKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  }

  async function handleMicClick() {
    if (sending || hasText) return;
    try {
      if (!recording) {
        await start();
        return;
      }
      const blob = await stop();
      if (blob) await onSendVoice(blob);
    } catch (err) {
      cancel();
      alert(err instanceof Error ? err.message : 'خطا در ضبط صدا');
    }
  }

  return (
    <form onSubmit={submit} className="tg-composer-bar shrink-0 border-t px-2 py-2.5 md:px-3">
      {recording ? (
        <div className="flex w-full items-center gap-3 rounded-full bg-[#242f3d] px-4 py-3">
          <span className="inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-red-500" />
          <span className="fa-num flex-1 text-sm text-red-300">
            در حال ضبط… {toPersianDigits(seconds)} ثانیه
          </span>
          <button
            type="button"
            onClick={cancel}
            className="rounded-lg px-3 py-1 text-sm text-[#8b9cb3] hover:bg-[#3d4f63]"
          >
            لغو
          </button>
          <button
            type="button"
            onClick={() => void handleMicClick()}
            className="tg-composer-action tg-btn-send flex items-center justify-center rounded-full text-white"
            title="ارسال ویس"
          >
            <IoSend className="h-5 w-5" />
          </button>
        </div>
      ) : (
        <div className="tg-composer-row">
          <button
            type="button"
            className="tg-composer-icon"
            title="پیوست (به‌زودی)"
          >
            <FaPaperclip className="h-5 w-5" />
          </button>

          <textarea
            ref={textareaRef}
            value={draft}
            onChange={(e) => onDraftChange(e.target.value)}
            onKeyDown={onKeyDown}
            rows={1}
            placeholder="پیام..."
            disabled={sending}
            className="tg-composer-input tg-input-field fa-num"
            onInput={(e) => growTextarea(e.currentTarget)}
          />

          {!hasText ? (
            <button
              type="button"
              disabled={sending}
              onClick={() => void handleMicClick()}
              className="tg-composer-action tg-btn-send"
              title="ضبط ویس"
            >
              <FaMicrophone className="h-5 w-5" />
            </button>
          ) : null}

          <button
            type="submit"
            disabled={sending || !hasText}
            className={`tg-composer-action tg-composer-send ${hasText ? 'tg-btn-send' : 'tg-composer-send-idle'}`}
            title="ارسال"
          >
            <IoSend className="h-5 w-5" />
          </button>
        </div>
      )}
    </form>
  );
}
