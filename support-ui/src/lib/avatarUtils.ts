export function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  return parts
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

const AVATAR_COLORS = [
  'bg-blue-500',
  'bg-green-500',
  'bg-yellow-500',
  'bg-red-500',
  'bg-purple-500',
  'bg-pink-500',
  'bg-indigo-500',
  'bg-teal-500',
  'bg-orange-500',
  'bg-cyan-500',
];

export function getAvatarColor(name: string): string {
  const hash = name.split('').reduce((acc, char) => char.charCodeAt(0) + ((acc << 5) - acc), 0);
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}
