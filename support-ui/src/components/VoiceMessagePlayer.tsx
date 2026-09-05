import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { IoPause, IoPlay } from 'react-icons/io5';
import { toPersianDigits } from '../lib/persianDigits';

interface VoiceMessagePlayerProps {
  src: string;
  messageId: string;
  isOwn: boolean;
}

let activeAudio: HTMLAudioElement | null = null;

function waveformBars(seed: string, count = 34): number[] {
  let hash = 0;
  for (let i = 0; i < seed.length; i += 1) {
    hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
  }
  const bars: number[] = [];
  for (let i = 0; i < count; i += 1) {
    hash = (hash * 1664525 + 1013904223) >>> 0;
    bars.push(0.22 + (hash % 78) / 100);
  }
  return bars;
}

function formatDuration(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
  const total = Math.floor(seconds);
  const m = Math.floor(total / 60);
  const s = total % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

export function VoiceMessagePlayer({ src, messageId, isOwn }: VoiceMessagePlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null);
  const [playing, setPlaying] = useState(false);
  const [current, setCurrent] = useState(0);
  const [duration, setDuration] = useState(0);

  const bars = useMemo(() => waveformBars(messageId), [messageId]);
  const progress = duration > 0 ? Math.min(1, current / duration) : 0;
  const timeLabel = toPersianDigits(formatDuration(playing ? current : duration || 0));

  const toggle = useCallback(() => {
    const audio = audioRef.current;
    if (!audio) return;
    if (audio.paused) {
      if (activeAudio && activeAudio !== audio) {
        activeAudio.pause();
      }
      activeAudio = audio;
      void audio.play();
    } else {
      audio.pause();
    }
  }, []);

  const seek = useCallback(
    (e: React.MouseEvent<HTMLDivElement>) => {
      const audio = audioRef.current;
      if (!audio || !duration) return;
      const rect = e.currentTarget.getBoundingClientRect();
      const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
      audio.currentTime = ratio * duration;
      setCurrent(audio.currentTime);
    },
    [duration],
  );

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return undefined;

    const onPlay = () => setPlaying(true);
    const onPause = () => setPlaying(false);
    const onTime = () => setCurrent(audio.currentTime);
    const onMeta = () => setDuration(audio.duration || 0);
    const onEnded = () => {
      setPlaying(false);
      setCurrent(0);
      if (activeAudio === audio) activeAudio = null;
    };

    audio.addEventListener('play', onPlay);
    audio.addEventListener('pause', onPause);
    audio.addEventListener('timeupdate', onTime);
    audio.addEventListener('loadedmetadata', onMeta);
    audio.addEventListener('durationchange', onMeta);
    audio.addEventListener('ended', onEnded);

    return () => {
      audio.removeEventListener('play', onPlay);
      audio.removeEventListener('pause', onPause);
      audio.removeEventListener('timeupdate', onTime);
      audio.removeEventListener('loadedmetadata', onMeta);
      audio.removeEventListener('durationchange', onMeta);
      audio.removeEventListener('ended', onEnded);
      if (activeAudio === audio) activeAudio = null;
    };
  }, [src]);

  return (
    <div
      className={`tg-voice-player ${isOwn ? 'is-own' : 'is-other'}`}
      onClick={(e) => e.stopPropagation()}
    >
      <button
        type="button"
        className="tg-voice-play"
        onClick={toggle}
        aria-label={playing ? 'توقف' : 'پخش'}
      >
        {playing ? <IoPause className="h-5 w-5" /> : <IoPlay className="h-5 w-5 translate-x-[1px]" />}
      </button>

      <div className="tg-voice-main">
        <div className="tg-voice-wave" onClick={seek} role="presentation">
          {bars.map((h, i) => {
            const filled = (i + 1) / bars.length <= progress;
            return (
              <span
                key={i}
                className={`tg-voice-bar ${filled ? 'is-filled' : ''}`}
                style={{ height: `${Math.round(h * 100)}%` }}
              />
            );
          })}
        </div>
        <span className="tg-voice-duration fa-num">{timeLabel}</span>
      </div>

      <audio ref={audioRef} preload="metadata" src={src} className="hidden" />
    </div>
  );
}
