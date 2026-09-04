import { useCallback, useEffect, useRef, useState } from 'react';

interface UserSubscriptionsModalProps {
  user: string;
  profileApiUrl: string;
  onClose: () => void;
}

export function UserSubscriptionsModal({ user, profileApiUrl, onClose }: UserSubscriptionsModalProps) {
  const hostRef = useRef<HTMLDivElement>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadProfile = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await fetch(
        `${profileApiUrl}?user=${encodeURIComponent(user)}&all=1`,
        { credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const html = await res.text();
      if (hostRef.current) hostRef.current.innerHTML = html;
    } catch {
      setError('خطا در بارگذاری اشتراک‌ها');
    } finally {
      setLoading(false);
    }
  }, [profileApiUrl, user]);

  useEffect(() => {
    const win = window as Window & {
      closeProfileModal?: () => void;
      copySub?: (button: HTMLButtonElement) => void;
      clearSubLink?: (button: HTMLButtonElement) => void;
      loadProfile?: (username: string) => void;
    };

    win.closeProfileModal = onClose;

    win.copySub = (button: HTMLButtonElement) => {
      const input = button.parentElement?.querySelector('input');
      if (!input) return;
      input.select();
      input.setSelectionRange(0, 99999);
      void navigator.clipboard.writeText(input.value);
      alert('کپی شد');
    };

    win.clearSubLink = (button: HTMLButtonElement) => {
      const targetUser = button.getAttribute('data-user') || '';
      const tracking = button.getAttribute('data-tracking') || '';
      const timestamp = button.getAttribute('data-timestamp') || '0';

      if (!targetUser || !tracking) {
        alert('اطلاعات اشتراک ناقص است');
        return;
      }

      if (!confirm('لینک این اشتراک از پنل کاربر حذف شود؟\nسابقه پرداخت باقی می‌ماند.')) {
        return;
      }

      button.disabled = true;
      button.textContent = '...';

      const body = new URLSearchParams();
      body.set('clear_link', '1');
      body.set('user', targetUser);
      body.set('tracking', tracking);
      body.set('timestamp', timestamp);

      fetch(profileApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString(),
        credentials: 'same-origin',
      })
        .then((res) => res.json())
        .then((data: { ok?: boolean; error?: string; message?: string }) => {
          if (!data?.ok) {
            alert(data?.error || 'حذف لینک ناموفق بود');
            button.disabled = false;
            button.textContent = 'حذف لینک';
            return;
          }
          alert(data.message || 'لینک حذف شد');
          void loadProfile();
        })
        .catch(() => {
          alert('خطا در ارتباط با سرور');
          button.disabled = false;
          button.textContent = 'حذف لینک';
        });
    };

    win.loadProfile = () => {
      void loadProfile();
    };

    void loadProfile();

    return () => {
      delete win.closeProfileModal;
      delete win.copySub;
      delete win.clearSubLink;
      delete win.loadProfile;
    };
  }, [loadProfile, onClose, profileApiUrl]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [onClose]);

  return (
    <div className="profile-host">
      {loading ? <div className="profile-host-loading">در حال بارگذاری اشتراک‌ها…</div> : null}
      {error ? (
        <div className="profile-host-error">
          {error}
          <button type="button" onClick={() => void loadProfile()}>
            تلاش مجدد
          </button>
        </div>
      ) : null}
      <div ref={hostRef} />
    </div>
  );
}
