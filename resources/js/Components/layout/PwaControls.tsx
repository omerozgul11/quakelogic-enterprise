import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import { Download } from 'lucide-react';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const LAST_KEY = 'ql_last_notif_id';

function playBeep() {
    try {
        const Ctx = window.AudioContext || (window as any).webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.value = 660;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
        osc.start(); osc.stop(ctx.currentTime + 0.36);
        osc.onended = () => ctx.close();
    } catch { /* sound blocked */ }
}

function showDesktop(title: string, body: string, url: string | null, sound: boolean) {
    const opts: NotificationOptions = { body, icon: '/icons/icon-192.png', badge: '/icons/icon-192.png', data: { url } } as NotificationOptions;
    if ('serviceWorker' in navigator && navigator.serviceWorker.ready) {
        navigator.serviceWorker.ready
            .then(reg => reg.showNotification(title, opts))
            .catch(() => { try { new Notification(title, opts); } catch { /* noop */ } });
    } else {
        try {
            const n = new Notification(title, opts);
            n.onclick = () => { window.focus(); if (url) window.location.href = url; n.close(); };
        } catch { /* noop */ }
    }
    if (sound) playBeep();
}

export function PwaControls() {
    const { auth } = usePage<SharedProps>().props;
    const channels = auth.user?.preferences?.channels;
    const [installEvt, setInstallEvt] = useState<BeforeInstallPromptEvent | null>(null);
    const seeded = useRef(false);

    // PWA install prompt capture.
    useEffect(() => {
        const onPrompt = (e: Event) => { e.preventDefault(); setInstallEvt(e as BeforeInstallPromptEvent); };
        const onInstalled = () => setInstallEvt(null);
        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);
        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    // Desktop notification poller.
    useEffect(() => {
        if (!channels?.desktop || typeof Notification === 'undefined') return;
        if (Notification.permission === 'default') Notification.requestPermission().catch(() => {});

        let timer: number | undefined;
        const poll = async () => {
            try {
                const r = await fetch('/notifications/feed', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) return;
                const data = await r.json();
                const latest = (data.latest ?? []) as Array<{ id: string; title: string; message: string | null; url: string | null }>;
                if (latest.length === 0) return;
                const newestId = latest[0].id;
                const lastSeen = localStorage.getItem(LAST_KEY);

                // First run only establishes a baseline — don't replay history.
                if (!seeded.current && lastSeen === null) {
                    localStorage.setItem(LAST_KEY, newestId);
                    seeded.current = true;
                    return;
                }
                seeded.current = true;
                if (newestId === lastSeen) return;

                // Notify for everything newer than lastSeen (only when not actively viewing).
                if (document.visibilityState !== 'visible' && Notification.permission === 'granted') {
                    for (const n of latest) {
                        if (n.id === lastSeen) break;
                        showDesktop(n.title, n.message ?? '', n.url, !!channels.sound);
                    }
                }
                localStorage.setItem(LAST_KEY, newestId);
            } catch { /* offline */ }
        };

        poll();
        timer = window.setInterval(poll, 30000);
        return () => { if (timer) window.clearInterval(timer); };
    }, [channels?.desktop, channels?.sound]);

    const install = async () => {
        if (!installEvt) return;
        await installEvt.prompt();
        await installEvt.userChoice;
        setInstallEvt(null);
    };

    if (!installEvt) return null;

    return (
        <button
            onClick={install}
            title="Install QuakeLogic as an app"
            className="hidden items-center gap-1.5 rounded-full border border-border bg-secondary/50 px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-secondary sm:inline-flex"
        >
            <Download className="h-3.5 w-3.5" /> Install app
        </button>
    );
}
