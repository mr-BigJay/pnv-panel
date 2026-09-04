import { FormEvent, KeyboardEvent, useRef } from 'react';
import { FaPaperclip, FaRegSmile } from 'react-icons/fa';
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

  async function submit(e?: FormEvent) {
    e?.preventDefault();
    if (recording) return;
    const text = draft.trim();
    if (!text || sending) return;
    onDraftChange('');
    if (textareaRef.current) textareaRef.current.style.height = '48px';
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
    <form
      onSubmit={submit}
      className="tg-composer-bar shrink-0 border-t px-2 py-2.5 md:px-3"
    >
      {recording ? (
        <div className="flex items-center gap-3 rounded-full bg-[#242f3d] px-4 py-3">
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
        <div className="tg-composer-row flex w-full items-end gap-2">
          <button
            type="button"
            className="tg-composer-icon mb-1 flex shrink-0 items-center justify-center rounded-full text-[#6d8399] hover:bg-[#242f3d] hover:text-[#6ab2f2]"
            title="پیوست (به‌زودی)"
          >
            <FaPaperclip className="h-5 w-5" />
          </button>

          <div className="tg-composer-field relative min-w-0 flex-1">
            <textarea
              ref={textareaRef}
              value={draft}
              onChange={(e) => onDraftChange(e.target.value)}
              onKeyDown={onKeyDown}
              rows={1}
              placeholder="پیام..."
              disabled={sending}
              className="tg-composer-input tg-input-field fa-num box-border w-full resize-none rounded-[22px] border-0 py-3 pl-4 pr-12 text-[15px] leading-normal outline-none disabled:opacity-60"
              onInput={(e) => {
                const el = e.currentTarget;
                el.style.height = 'auto';
                el.style.height = `${Math.min(el.scrollHeight, 140)}px`;
              }}
            />
            <button
              type="button"
              tabIndex={-1}
              className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-[#6d8399] hover:text-[#6ab2f2]"
              title="ایموجی (به‌زودی)"
            >
              <FaRegSmile className="h-5 w-5" />
            </button>
          </div>

          <div className="mb-1 flex shrink-0 items-end gap-1">
            {!hasText ? (
              <button
                type="button"
                disabled={sending}
                onClick={() => void handleMicClick()}
                className="tg-composer-action tg-btn-send flex items-center justify-center rounded-full text-white disabled:opacity-50"
                title="ضبط ویس"
              >
                <FaMicrophone className="h-5 w-5" />
              </button>
            ) : null}

            <button
              type="submit"
              disabled={sending || !hasText}
              className={`tg-composer-action tg-composer-send flex items-center justify-center rounded-full text-white disabled:cursor-not-allowed ${
                hasText ? 'tg-btn-send' : 'bg-[#3d4f63] opacity-70'
              }`}
              title="ارسال"
            >
              <IoSend className="h-5 w-5" />
            </button>
          </div>
        </div>
      )}
    </form>
  );
}
