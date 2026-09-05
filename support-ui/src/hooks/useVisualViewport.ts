import { useEffect } from 'react';

export function useVisualViewport(onChange?: () => void) {
  useEffect(() => {
    const vv = window.visualViewport;
    if (!vv) return undefined;

    const apply = () => {
      const height = Math.round(vv.height);
      const offsetTop = Math.round(vv.offsetTop);
      const keyboardInset = Math.max(0, window.innerHeight - height - offsetTop);
      const root = document.documentElement;

      root.style.setProperty('--tg-vv-height', `${height}px`);
      root.style.setProperty('--tg-vv-offset-top', `${offsetTop}px`);
      root.style.setProperty('--tg-keyboard-inset', `${keyboardInset}px`);

      if (keyboardInset > 48) {
        root.classList.add('tg-keyboard-open');
      } else {
        root.classList.remove('tg-keyboard-open');
      }

      onChange?.();
    };

    apply();
    vv.addEventListener('resize', apply);
    vv.addEventListener('scroll', apply);
    window.addEventListener('orientationchange', apply);

    return () => {
      vv.removeEventListener('resize', apply);
      vv.removeEventListener('scroll', apply);
      window.removeEventListener('orientationchange', apply);
      const root = document.documentElement;
      root.style.removeProperty('--tg-vv-height');
      root.style.removeProperty('--tg-vv-offset-top');
      root.style.removeProperty('--tg-keyboard-inset');
      root.classList.remove('tg-keyboard-open');
    };
  }, [onChange]);
}
