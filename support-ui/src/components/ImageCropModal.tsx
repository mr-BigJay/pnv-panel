import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

interface CropRect {
  x: number;
  y: number;
  w: number;
  h: number;
}

type DragMode = 'move' | 'nw' | 'ne' | 'sw' | 'se' | 'n' | 's' | 'e' | 'w';

interface ImageCropModalProps {
  file: File;
  onConfirm: (file: File) => void;
  onCancel: () => void;
}

const MIN_SIZE = 48;
const HANDLE = 16;

function pointerPos(canvas: HTMLCanvasElement, e: React.TouchEvent | React.MouseEvent) {
  const rect = canvas.getBoundingClientRect();
  const pt = 'touches' in e && e.touches[0] ? e.touches[0] : (e as React.MouseEvent);
  return { x: pt.clientX - rect.left, y: pt.clientY - rect.top };
}

function clampRect(rect: CropRect, maxW: number, maxH: number): CropRect {
  let { x, y, w, h } = rect;
  w = Math.max(MIN_SIZE, Math.min(w, maxW));
  h = Math.max(MIN_SIZE, Math.min(h, maxH));
  x = Math.max(0, Math.min(x, maxW - w));
  y = Math.max(0, Math.min(y, maxH - h));
  return { x, y, w, h };
}

function hitTest(p: { x: number; y: number }, rect: CropRect): DragMode | null {
  const { x, y, w, h } = rect;
  const near = (ax: number, ay: number) => Math.abs(p.x - ax) <= HANDLE && Math.abs(p.y - ay) <= HANDLE;

  if (near(x, y)) return 'nw';
  if (near(x + w, y)) return 'ne';
  if (near(x, y + h)) return 'sw';
  if (near(x + w, y + h)) return 'se';

  if (Math.abs(p.y - y) <= HANDLE && p.x >= x && p.x <= x + w) return 'n';
  if (Math.abs(p.y - (y + h)) <= HANDLE && p.x >= x && p.x <= x + w) return 's';
  if (Math.abs(p.x - x) <= HANDLE && p.y >= y && p.y <= y + h) return 'w';
  if (Math.abs(p.x - (x + w)) <= HANDLE && p.y >= y && p.y <= y + h) return 'e';

  if (p.x >= x && p.x <= x + w && p.y >= y && p.y <= y + h) return 'move';
  return null;
}

function applyDrag(mode: DragMode, start: CropRect, dx: number, dy: number, maxW: number, maxH: number): CropRect {
  let { x, y, w, h } = start;

  if (mode === 'move') {
    return clampRect({ x: x + dx, y: y + dy, w, h }, maxW, maxH);
  }

  if (mode.includes('e')) w = w + dx;
  if (mode.includes('w')) {
    w = w - dx;
    x = x + dx;
  }
  if (mode.includes('s')) h = h + dy;
  if (mode.includes('n')) {
    h = h - dy;
    y = y + dy;
  }

  return clampRect({ x, y, w, h }, maxW, maxH);
}

function CropDialog({ file, onConfirm, onCancel }: ImageCropModalProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const sourceRef = useRef<HTMLImageElement | null>(null);
  const scaleRef = useRef(1);
  const dragRef = useRef<{
    mode: DragMode;
    x: number;
    y: number;
    crop: CropRect;
  } | null>(null);
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

    const corners = [
      [rect.x, rect.y],
      [rect.x + rect.w, rect.y],
      [rect.x, rect.y + rect.h],
      [rect.x + rect.w, rect.y + rect.h],
    ];
    ctx.fillStyle = '#6ab2f2';
    corners.forEach(([cx, cy]) => {
      ctx.fillRect(cx - 4, cy - 4, 8, 8);
    });
  }, []);

  useEffect(() => {
    let cancelled = false;
    const reader = new FileReader();
    reader.onload = () => {
      const img = new Image();
      img.onload = () => {
        if (cancelled) return;
        sourceRef.current = img;
        const maxW = Math.min(window.innerWidth - 32, window.innerHeight * 0.55);
        const scale = Math.min(1, maxW / img.width, maxW / img.height);
        scaleRef.current = scale;
        const cw = Math.round(img.width * scale);
        const ch = Math.round(img.height * scale);
        const margin = Math.round(Math.min(cw, ch) * 0.06);
        const initial = clampRect(
          { x: margin, y: margin, w: cw - margin * 2, h: ch - margin * 2 },
          cw,
          ch,
        );
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
    const mode = hitTest(p, crop);
    if (!mode) return;
    dragRef.current = { mode, x: p.x, y: p.y, crop: { ...crop } };
  }

  function onMove(e: React.TouchEvent | React.MouseEvent) {
    if (!dragRef.current) return;
    e.preventDefault();
    const canvas = canvasRef.current;
    if (!canvas) return;
    const p = pointerPos(canvas, e);
    const drag = dragRef.current;
    const dx = p.x - drag.x;
    const dy = p.y - drag.y;
    setCrop(applyDrag(drag.mode, drag.crop, dx, dy, canvas.width, canvas.height));
  }

  function onUp() {
    dragRef.current = null;
  }

  function handleConfirm() {
    const img = sourceRef.current;
    if (!img) return;
    const scale = scaleRef.current;
    const outW = Math.max(32, Math.round(crop.w / scale));
    const outH = Math.max(32, Math.round(crop.h / scale));
    const out = document.createElement('canvas');
    out.width = outW;
    out.height = outH;
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
      outW,
      outH,
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
    <div className="support-crop-overlay" role="presentation">
      <div className="support-crop-card" onClick={(e) => e.stopPropagation()}>
        <div className="support-crop-title">برش تصویر</div>
        <p className="support-crop-hint">گوشه‌ها را بکشید تا اندازه تغییر کند</p>
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

export function ImageCropModal(props: ImageCropModalProps) {
  return createPortal(<CropDialog {...props} />, document.body);
}
