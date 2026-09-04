const PIN_PREFIX = 'support_pins_';

export function pinStorageKey(scope: string): string {
  return `${PIN_PREFIX}${scope || 'default'}`;
}

export function loadPinnedIds(scope: string): string[] {
  try {
    const raw = localStorage.getItem(pinStorageKey(scope));
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed.map(String) : [];
  } catch {
    return [];
  }
}

export function savePinnedIds(scope: string, ids: string[]): void {
  try {
    localStorage.setItem(pinStorageKey(scope), JSON.stringify(ids));
  } catch {
    /* ignore quota errors */
  }
}

export function isPinned(scope: string, messageId: string): boolean {
  return loadPinnedIds(scope).includes(messageId);
}

export function togglePin(scope: string, messageId: string): boolean {
  const ids = loadPinnedIds(scope);
  const idx = ids.indexOf(messageId);
  if (idx >= 0) {
    ids.splice(idx, 1);
    savePinnedIds(scope, ids);
    return false;
  }
  ids.push(messageId);
  savePinnedIds(scope, ids);
  return true;
}
