import { useEffect } from 'react';
import { IoClose } from 'react-icons/io5';

interface ImageLightboxProps {
  src: string;
  onClose: () => void;
}

export function ImageLightbox({ src, onClose }: ImageLightboxProps) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  return (
    <div className="support-image-lightbox" onClick={onClose} role="presentation">
      <button
        type="button"
        className="support-image-lightbox-close"
        onClick={onClose}
        aria-label="بستن"
      >
        <IoClose className="h-6 w-6" />
      </button>
      <img src={src} alt="" className="support-image-lightbox-img" onClick={(e) => e.stopPropagation()} />
    </div>
  );
}
