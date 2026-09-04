export interface SupportConfig {
  apiUrl: string;
  csrf: string;
  embedded: boolean;
  initialUser: string;
  pollIntervalMs: number;
}

export interface Ticket {
  user: string;
  initial: string;
  preview: string;
  relative_time: string;
  timestamp: number;
  unread: number;
  status: string;
  mobile: string;
  ticket_id: string;
}

export interface SupportMessage {
  id: string;
  sender: string;
  text: string;
  image: string;
  date: string;
  time: string;
  timestamp: number;
  edited: boolean;
  reply_to: Record<string, unknown> | null;
  seen_by_admin: boolean;
  seen_by_user: boolean;
  is_own: boolean;
}

export interface TicketsResponse {
  tickets: Ticket[];
  has_unread: boolean;
  unread_count: number;
}

export interface MessagesResponse {
  user: string;
  messages: SupportMessage[];
  status: string;
  unreadUsers: string[];
  has_unread: boolean;
  unread_count: number;
  sync?: SupportMessage[];
}

export interface BootstrapResponse {
  csrf: string;
  embedded: boolean;
  poll_interval_ms: number;
}

declare global {
  interface Window {
    SUPPORT_CONFIG?: Partial<SupportConfig>;
  }
}

export function getSupportConfig(): SupportConfig {
  const cfg = window.SUPPORT_CONFIG ?? {};
  return {
    apiUrl: cfg.apiUrl ?? 'support-api.php',
    csrf: cfg.csrf ?? '',
    embedded: Boolean(cfg.embedded),
    initialUser: cfg.initialUser ?? '',
    pollIntervalMs: cfg.pollIntervalMs ?? 3000,
  };
}
