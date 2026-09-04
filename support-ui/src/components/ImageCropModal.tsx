import { useCallback, useEffect, useRef, useState } from 'react';

interface CropRect {
  x: number;
  y: number;
  w: number;
  h: number;
}

interface ImageCropModalProps {
  file: File;
  onConfirm: (file: File) => void;
  onCancel: () => void;
}

function pointerPos(canvas: HTMLCanvasElement, e: React.TouchEvent | React.MouseEvent) {
  const rect = canvas.getBoundingClientRect();
  const pt = 'touches' in e && e.touches[0] ? e.touches[0] : (e as React.MouseEvent);
  return { x: pt.clientX - rect.left, y: pt.clientY - rect.top };
}

export function ImageCropModal({ file, onConfirm, onCancel }: ImageCropModalProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const sourceRef = useRef<HTMLImageElement | null>(null);
  const scaleRef = useRef(1);
  const dragRef = useRef<{ x: number; y: number; cx: number; cy: number } | null>(null);
  const [crop, setCrop] = useState<CropRect>({ x: 0, y: 0, w: 0, h: 0 });
  const [ready, setReady] = useState(false);

  const draw = useCallback((img: HTMLImageElement, rect: CropRect, scale: number) => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = Math.round(img.width * scale);
    canvas.height = Math.round(img.height * scale);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'rgba(0,0,0,.45)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.clearRect(rect.x, rect.y, rect.w, rect.h);
    ctx.drawImage(
      img,
      rect.x / scale,
      rect.y / scale,
      rect.w / scale,
      rect.h / scale,
      rect.x,
      rect.y,
      rect.w,
      rect.h,
    );
    ctx.strokeStyle = '#6ab2f2';
    ctx.lineWidth = 2;
    ctx.strokeRect(rect.x, rect.y, rect.w, rect.h);
  }, []);

  useEffect(() => {
    let cancelled = false;
    const reader = new FileReader();
    reader.onload = () => {
      const img = new Image();
      img.onload = () => {
        if (cancelled) return;
        sourceRef.current = img;
        const maxW = Math.min(360, window.innerWidth - 32);
        const scale = Math.min(1, maxW / img.width);
        scaleRef.current = scale;
        const cw = Math.round(img.width * scale);
        const ch = Math.round(img.height * scale);
        const side = Math.min(cw, ch);
        const initial = {
          x: Math.round((cw - side) / 2),
          y: Math.round((ch - side) / 2),
          w: side,
          h: side,
        };
        setCrop(initial);
        draw(img, initial, scale);
        setReady(true);
      };
      img.onerror = () => alert('این تصویر قابل برش نیست.');
      img.src = String(reader.result);
    };
    reader.readAsDataURL(file);
    return () => {
      cancelled = true;
    };
  }, [draw, file]);

  useEffect(() => {
    const img = sourceRef.current;
    if (!img || !ready) return;
    draw(img, crop, scaleRef.current);
  }, [crop, draw, ready]);

  function onDown(e: React.TouchEvent | React.MouseEvent) {
    e.preventDefault();
    const canvas = canvasRef.current;
    if (!canvas) return;
    const p = pointerPos(canvas, e);
    dragRef.current = { x: p.x, y: p.y, cx: crop.x, cy: crop.y };
  }

  function onMove(e: React.TouchEvent | React.MouseEvent) {
    if (!dragRef.current) return;
    e.preventDefault();
    const canvas = canvasRef.current;
    if (!canvas) return;
    const p = pointerPos(canvas, e);
    const drag = dragRef.current;
    setCrop((prev) => ({
      ...prev,
      x: Math.max(0, Math.min(canvas.width - prev.w, drag.cx + (p.x - drag.x))),
      y: Math.max(0, Math.min(canvas.height - prev.h, drag.cy + (p.y - drag.y))),
    }));
  }

  function onUp() {
    dragRef.current = null;
  }

  function handleConfirm() {
    const img = sourceRef.current;
    const canvas = canvasRef.current;
    if (!img || !canvas) return;
    const scale = scaleRef.current;
    const size = Math.max(32, Math.round(crop.w / scale));
    const out = document.createElement('canvas');
    out.width = size;
    out.height = size;
    const ctx = out.getContext('2d');
    if (!ctx) return;
    ctx.drawImage(
      img,
      crop.x / scale,
      crop.y / scale,
      crop.w / scale,
      crop.h / scale,
      0,
      0,
      size,
      size,
    );
    out.toBlob(
      (blob) => {
        if (!blob) {
          alert('برش تصویر ناموفق بود');
          return;
        }
        onConfirm(new File([blob], 'support-crop.jpg', { type: 'image/jpeg' }));
      },
      'image/jpeg',
      0.88,
    );
  }

  return (
    <div className="support-crop-overlay" onClick={onCancel} role="presentation">
      <div className="support-crop-card" onClick={(e) => e.stopPropagation()}>
        <div className="support-crop-title">برش تصویر</div>
        <div className="support-crop-stage">
          <canvas
            ref={canvasRef}
            onMouseDown={onDown}
            onMouseMove={onMove}
            onMouseUp={onUp}
            onMouseLeave={onUp}
            onTouchStart={onDown}
            onTouchMove={onMove}
            onTouchEnd={onUp}
          />
        </div>
        <div className="support-crop-actions">
          <button type="button" className="support-crop-btn ghost" onClick={onCancel}>
            انصراف
          </button>
          <button type="button" className="support-crop-btn" onClick={handleConfirm} disabled={!ready}>
            تأیید
          </button>
        </div>
      </div>
    </div>
  );
}
