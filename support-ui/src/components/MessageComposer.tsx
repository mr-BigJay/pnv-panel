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
  onSendText: () => Promise<void>;
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
    if (!hasText || sending) return;
    onDraftChange('');
    if (textareaRef.current) textareaRef.current.style.height = '44px';
    await onSendText();
  }

  function onKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  }

  async function handleMicClick() {
    if (sending) return;
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
    <form onSubmit={submit} className="border-t border-[#212121]/50 bg-[#101010]/95 p-3 backdrop-blur-sm md:p-4">
      {recording ? (
        <div className="flex items-center gap-3 rounded-2xl bg-[#212121] px-4 py-3">
          <span className="inline-flex h-3 w-3 animate-pulse rounded-full bg-red-500" />
          <span className="fa-num flex-1 text-sm text-red-300">
            در حال ضبط… {toPersianDigits(seconds)} ثانیه
          </span>
          <button
            type="button"
            onClick={cancel}
            className="rounded-lg px-3 py-1.5 text-sm text-gray-300 hover:bg-gray-700"
          >
            لغو
          </button>
          <button
            type="button"
            onClick={() => void handleMicClick()}
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600"
            title="ارسال ویس"
          >
            <IoSend className="h-5 w-5" />
          </button>
        </div>
      ) : (
        <div className="flex items-end gap-2">
          <button
            type="button"
            className="mb-0.5 shrink-0 rounded-lg p-2.5 text-gray-400 hover:bg-gray-800"
            title="پیوست"
          >
            <FaPaperclip className="h-5 w-5" />
          </button>

          <div className="relative min-w-0 flex-1">
            <textarea
              ref={textareaRef}
              value={draft}
              onChange={(e) => onDraftChange(e.target.value)}
              onKeyDown={onKeyDown}
              rows={1}
              placeholder="پیام..."
              disabled={sending}
              className="fa-num max-h-[120px] min-h-[44px] w-full resize-none rounded-2xl border-0 bg-[#212121] py-3 pl-12 pr-12 text-white outline-none placeholder:text-[#a2acb4] disabled:opacity-60"
              onInput={(e) => {
                const el = e.currentTarget;
                el.style.height = 'auto';
                el.style.height = `${Math.min(el.scrollHeight, 120)}px`;
              }}
            />
            <button
              type="button"
              className="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:text-gray-200"
              title="ایموجی"
            >
              <FaRegSmile className="h-5 w-5" />
            </button>
          </div>

          {hasText ? (
            <button
              type="submit"
              disabled={sending}
              className="mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
              title="ارسال"
            >
              <IoSend className="h-5 w-5" />
            </button>
          ) : (
            <button
              type="button"
              disabled={sending}
              onClick={() => void handleMicClick()}
              className="mb-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 disabled:opacity-50"
              title="ضبط ویس"
            >
              <FaMicrophone className="h-5 w-5" />
            </button>
          )}
        </div>
      )}
    </form>
  );
}
