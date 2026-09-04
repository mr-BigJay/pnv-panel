import { useEffect, useMemo, useState } from 'react';
import { IoSend } from 'react-icons/io5';
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

  return (
    <>
      <div className="support-attach-sheet">
        <div className="support-attach-header">
          <button type="button" className="support-attach-cancel" onClick={onCancel} disabled={sending}>
            انصراف
          </button>
          <span className="support-attach-title">ارسال تصویر</span>
          <button
            type="button"
            className="support-attach-crop"
            onClick={() => setShowCrop(true)}
            disabled={sending}
          >
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
            onClick={() => void onSend(pendingFile, caption.trim())}
            title="ارسال"
          >
            <IoSend className="h-5 w-5" />
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
    </>
  );
}
