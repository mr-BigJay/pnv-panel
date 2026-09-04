import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { IoSend } from 'react-icons/io5';

interface ImageAttachSheetProps {
  file: File;
  initialCaption: string;
  sending: boolean;
  onCancel: () => void;
  onRecrop: () => void;
  onSend: (file: File, caption: string) => Promise<void>;
}

export function ImageAttachSheet({
  file,
  initialCaption,
  sending,
  onCancel,
  onRecrop,
  onSend,
}: ImageAttachSheetProps) {
  const [caption, setCaption] = useState(initialCaption);
  const previewUrl = useMemo(() => URL.createObjectURL(file), [file]);

  useEffect(() => () => URL.revokeObjectURL(previewUrl), [previewUrl]);

  return createPortal(
    <div className="support-attach-overlay" role="dialog" aria-modal="true" aria-label="ارسال تصویر">
      <div className="support-attach-sheet">
        <div className="support-attach-header">
          <button type="button" className="support-attach-cancel" onClick={onCancel} disabled={sending}>
            انصراف
          </button>
          <span className="support-attach-title">ارسال تصویر</span>
          <button type="button" className="support-attach-crop" onClick={onRecrop} disabled={sending}>
            برش
          </button>
        </div>
        <div className="support-attach-preview">
          <img src={previewUrl} alt="" className="support-attach-preview-img" />
        </div>
        <div className="support-attach-caption-row">
          <textarea
            value={caption}
            onChange={(e) => setCaption(e.target.value)}
            placeholder="کپشن (اختیاری)..."
            rows={2}
            disabled={sending}
            className="support-attach-caption fa-num"
          />
          <button
            type="button"
            className="tg-composer-action tg-btn-send support-attach-send"
            disabled={sending}
            onClick={() => void onSend(file, caption.trim())}
            title="ارسال"
          >
            <IoSend className="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
