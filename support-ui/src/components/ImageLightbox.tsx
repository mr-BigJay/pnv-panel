import { useCallback, useEffect, useRef, useState } from 'react';
import { IoClose } from 'react-icons/io5';

interface ImageLightboxProps {
  src: string;
  onClose: () => void;
}

interface Transform {
  scale: number;
  x: number;
  y: number;
}

function touchDistance(a: Touch, b: Touch) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
}

export function ImageLightbox({ src, onClose }: ImageLightboxProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [transform, setTransform] = useState<Transform>({ scale: 1, x: 0, y: 0 });
  const transformRef = useRef(transform);
  transformRef.current = transform;
  const pinchRef = useRef<{ dist: number; scale: number } | null>(null);
  const panRef = useRef<{ x: number; y: number; tx: number; ty: number } | null>(null);

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

  const onTouchStart = useCallback((e: TouchEvent) => {
    const t = transformRef.current;
    if (e.touches.length === 2) {
      e.preventDefault();
      pinchRef.current = {
        dist: touchDistance(e.touches[0], e.touches[1]),
        scale: t.scale,
      };
      panRef.current = null;
    } else if (e.touches.length === 1 && t.scale > 1.05) {
      panRef.current = {
        x: e.touches[0].clientX,
        y: e.touches[0].clientY,
        tx: t.x,
        ty: t.y,
      };
    }
  }, []);

  const onTouchMove = useCallback((e: TouchEvent) => {
    if (e.touches.length === 2 && pinchRef.current) {
      e.preventDefault();
      const dist = touchDistance(e.touches[0], e.touches[1]);
      const nextScale = Math.min(5, Math.max(1, pinchRef.current.scale * (dist / pinchRef.current.dist)));
      setTransform((t) => ({ ...t, scale: nextScale }));
    } else if (e.touches.length === 1 && panRef.current) {
      e.preventDefault();
      const dx = e.touches[0].clientX - panRef.current.x;
      const dy = e.touches[0].clientY - panRef.current.y;
      setTransform((t) => ({
        ...t,
        x: panRef.current!.tx + dx,
        y: panRef.current!.ty + dy,
      }));
    }
  }, []);

  const onTouchEnd = useCallback(() => {
    pinchRef.current = null;
    panRef.current = null;
    setTransform((t) => (t.scale < 1.05 ? { scale: 1, x: 0, y: 0 } : t));
  }, []);

  useEffect(() => {
    const el = viewportRef.current;
    if (!el) return undefined;
    el.addEventListener('touchstart', onTouchStart, { passive: false });
    el.addEventListener('touchmove', onTouchMove, { passive: false });
    el.addEventListener('touchend', onTouchEnd);
    el.addEventListener('touchcancel', onTouchEnd);
    return () => {
      el.removeEventListener('touchstart', onTouchStart);
      el.removeEventListener('touchmove', onTouchMove);
      el.removeEventListener('touchend', onTouchEnd);
      el.removeEventListener('touchcancel', onTouchEnd);
    };
  }, [onTouchEnd, onTouchMove, onTouchStart]);

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
      <div ref={viewportRef} className="support-image-lightbox-viewport" onClick={(e) => e.stopPropagation()}>
        <img
          src={src}
          alt=""
          draggable={false}
          className="support-image-lightbox-img"
          style={{
            transform: `translate(${transform.x}px, ${transform.y}px) scale(${transform.scale})`,
          }}
        />
      </div>
    </div>
  );
}
