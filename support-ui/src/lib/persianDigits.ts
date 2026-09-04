const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

export function toPersianDigits(value: string | number): string {
  return String(value).replace(/\d/g, (d) => PERSIAN_DIGITS[parseInt(d, 10)] ?? d);
}

export function formatPersianDate(date: string): string {
  return toPersianDigits(date);
}

export function formatPersianTime(time: string): string {
  return toPersianDigits(time);
}
