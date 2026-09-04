import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { IoArrowBack, IoSend } from 'react-icons/io5';
import { MdCrop } from 'react-icons/md';
import { ImageCropModal } from './ImageCropModal';

interface ImageAttachSheetProps {
  file: File;
  initialCaption: string;
  sending: boolean;
  onCancel: () => void;
  onSend: (file: File, caption: string) => Promise<void>;
}

export function ImageAttachSheet({
  file,
  initialCaption,
  sending,
  onCancel,
  onSend,
}: ImageAttachSheetProps) {
  const [pendingFile, setPendingFile] = useState(file);
  const [caption, setCaption] = useState(initialCaption);
  const [showCrop, setShowCrop] = useState(false);
  const previewUrl = useMemo(() => URL.createObjectURL(pendingFile), [pendingFile]);

  useEffect(() => () => URL.revokeObjectURL(previewUrl), [previewUrl]);

  return createPortal(
    <>
      <div className="support-media-overlay" role="dialog" aria-modal="true" aria-label="ارسال تصویر">
        <div className="support-media-preview">
          <img src={previewUrl} alt="" className="support-media-preview-img" />
        </div>

        <div className="support-media-caption-wrap">
          <textarea
            value={caption}
            onChange={(e) => setCaption(e.target.value)}
            placeholder="کپشن (اختیاری)..."
            rows={1}
            disabled={sending}
            className="support-media-caption fa-num"
          />
        </div>

        <div className="support-media-toolbar">
          <button
            type="button"
            className="support-media-tool-btn"
            onClick={onCancel}
            disabled={sending}
            aria-label="برگشت"
            title="برگشت"
          >
            <IoArrowBack className="h-6 w-6" />
          </button>
          <button
            type="button"
            className="support-media-tool-btn"
            onClick={() => setShowCrop(true)}
            disabled={sending}
            aria-label="برش"
            title="برش"
          >
            <MdCrop className="h-7 w-7" />
          </button>
          <button
            type="button"
            className="support-media-tool-btn support-media-tool-send"
            disabled={sending}
            onClick={() => void onSend(pendingFile, caption.trim())}
            aria-label="ارسال"
            title="ارسال"
          >
            <IoSend className="h-6 w-6" />
          </button>
        </div>
      </div>

      {showCrop ? (
        <ImageCropModal
          file={pendingFile}
          onCancel={() => setShowCrop(false)}
          onConfirm={(cropped) => {
            setPendingFile(cropped);
            setShowCrop(false);
          }}
        />
      ) : null}
    </>,
    document.body,
  );
}
