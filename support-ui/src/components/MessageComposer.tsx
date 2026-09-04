import { ChangeEvent, FormEvent, KeyboardEvent, forwardRef, useImperativeHandle, useRef, useState } from 'react';
import { FaPaperclip } from 'react-icons/fa';
import { FaMicrophone } from 'react-icons/fa6';
import { IoSend } from 'react-icons/io5';
import { useVoiceRecorder } from '../hooks/useVoiceRecorder';
import { toPersianDigits } from '../lib/persianDigits';
import { ComposerChip } from './ComposerChip';
import { ImageAttachSheet } from './ImageAttachSheet';
import type { ReplyTarget } from '../types';

export interface MessageComposerHandle {
  focus: () => void;
}

interface MessageComposerProps {
  draft: string;
  sending: boolean;
  replyTarget?: ReplyTarget | null;
  editTarget?: ReplyTarget | null;
  onDraftChange: (value: string) => void;
  onClearReply: () => void;
  onClearEdit: () => void;
  onSendText: (text: string) => Promise<void>;
  onSendVoice: (blob: Blob) => Promise<void>;
  onSendImage: (file: File, caption?: string) => Promise<void>;
  showVoice?: boolean;
  showAttach?: boolean;
}

const IMAGE_ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';

export const MessageComposer = forwardRef<MessageComposerHandle, MessageComposerProps>(
  function MessageComposer(
    {
      draft,
      sending,
      replyTarget,
      editTarget,
      onDraftChange,
      onClearReply,
      onClearEdit,
      onSendText,
      onSendVoice,
      onSendImage,
      showVoice = true,
      showAttach = true,
    },
    ref,
  ) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [attachFile, setAttachFile] = useState<File | null>(null);
    const { recording, seconds, start, stop, cancel } = useVoiceRecorder();
    const hasText = draft.trim().length > 0;
    const isEditing = Boolean(editTarget);

    useImperativeHandle(ref, () => ({
      focus: () => textareaRef.current?.focus(),
    }));

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

    function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
      const file = e.target.files?.[0];
      e.target.value = '';
      if (!file || sending || isEditing) return;

      const okType =
        /^image\/(jpeg|jpg|png|webp)$/i.test(file.type) ||
        /\.(jpe?g|png|webp)$/i.test(file.name);
      if (!okType) {
        alert('فقط JPG، PNG یا WebP مجاز است');
        return;
      }

      setAttachFile(file);
    }

    async function handleAttachSend(file: File, caption: string) {
      if (caption && caption === draft.trim()) onDraftChange('');
      growTextarea(textareaRef.current);
      await onSendImage(file, caption);
      setAttachFile(null);
    }

    return (
      <div className="tg-composer-wrap shrink-0 border-t border-[#0e1621] bg-[#17212b]">
        {attachFile ? (
          <ImageAttachSheet
            file={attachFile}
            initialCaption={draft.trim()}
            sending={sending}
            onCancel={() => setAttachFile(null)}
            onSend={handleAttachSend}
          />
        ) : null}
        {replyTarget ? (
          <ComposerChip
            title="در پاسخ به"
            preview={replyTarget.text || 'پیام'}
            onClear={onClearReply}
          />
        ) : null}
        {editTarget ? (
          <ComposerChip title="ویرایش پیام" preview={editTarget.text || 'پیام'} onClear={onClearEdit} />
        ) : null}

        {!attachFile ? (
        <form onSubmit={submit} className="tg-composer-bar px-2 py-2.5 md:px-3">
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
              {showAttach && !isEditing ? (
                <>
                  <button
                    type="button"
                    className="tg-composer-icon"
                    title="پیوست تصویر"
                    disabled={sending}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <FaPaperclip className="h-5 w-5" />
                  </button>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept={IMAGE_ACCEPT}
                    className="hidden"
                    onChange={(e) => void handleFileChange(e)}
                  />
                </>
              ) : null}

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

              {hasText ? (
                <button
                  type="submit"
                  disabled={sending}
                  className="tg-composer-action tg-btn-send"
                  title="ارسال"
                >
                  <IoSend className="h-5 w-5" />
                </button>
              ) : showVoice ? (
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
            </div>
          )}
        </form>
        ) : null}
      </div>
    );
  },
);
