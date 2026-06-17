/**
 * QuakeBot conversation persistence. The chat is kept in localStorage per user
 * so it survives navigation between sections (each chat surface re-mounts and
 * rehydrates from here) and is shared live, in the same tab, between the sidebar
 * popover and the full Ask-QuakeAI panel. It is cleared on logout and whenever a
 * different user signs in.
 */
export interface ChatMsg {
    role: 'user' | 'assistant';
    content: string;
}

const EVENT = 'quakeai:chat-changed';
const OWNER_KEY = 'quakeai:chat:owner';
const chatKey = (uid: string | number) => `quakeai:chat:${uid}`;

function safe<T>(fn: () => T, fallback: T): T {
    try {
        return fn();
    } catch {
        return fallback;
    }
}

/**
 * Load a user's saved conversation. If a different user owned the stored chat,
 * drop it first so people never see each other's history on a shared device.
 */
export function loadChat(uid: string | number): ChatMsg[] {
    return safe(() => {
        const prev = localStorage.getItem(OWNER_KEY);
        if (prev !== null && prev !== String(uid)) {
            localStorage.removeItem(chatKey(prev));
        }
        localStorage.setItem(OWNER_KEY, String(uid));
        const raw = localStorage.getItem(chatKey(uid));
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    }, []);
}

/** Persist the conversation and notify other chat surfaces mounted in this tab. */
export function saveChat(uid: string | number, messages: ChatMsg[]): void {
    safe(() => {
        localStorage.setItem(chatKey(uid), JSON.stringify(messages.slice(-60)));
        window.dispatchEvent(new CustomEvent(EVENT));
        return null;
    }, null);
}

/** Wipe the stored conversation — called on logout. */
export function clearChat(): void {
    safe(() => {
        const owner = localStorage.getItem(OWNER_KEY);
        if (owner) localStorage.removeItem(chatKey(owner));
        localStorage.removeItem(OWNER_KEY);
        window.dispatchEvent(new CustomEvent(EVENT));
        return null;
    }, null);
}

/** Subscribe to in-tab chat changes; returns an unsubscribe function. */
export function onChatChanged(handler: () => void): () => void {
    window.addEventListener(EVENT, handler);
    return () => window.removeEventListener(EVENT, handler);
}
