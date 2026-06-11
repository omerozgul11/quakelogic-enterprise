import { useEffect, useRef } from 'react';

/**
 * Lightweight, dependency-free confetti burst. Renders a fixed full-screen
 * canvas, fires a couple of bursts, then clears itself. Purely decorative
 * (pointer-events-none) so it never blocks the UI underneath.
 */
export function Confetti({ duration = 2600 }: { duration?: number }) {
    const ref = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = ref.current;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !ctx) return;

        const resize = () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; };
        resize();

        const colors = ['#f26522', '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#06b6d4'];
        type P = { x: number; y: number; vx: number; vy: number; rot: number; vr: number; size: number; color: string; round: boolean };
        const parts: P[] = [];

        const burst = (n: number, originX: number) => {
            for (let i = 0; i < n; i++) {
                parts.push({
                    x: originX,
                    y: window.innerHeight * 0.4,
                    vx: (Math.random() - 0.5) * 13,
                    vy: -Math.random() * 14 - 5,
                    rot: Math.random() * Math.PI,
                    vr: (Math.random() - 0.5) * 0.32,
                    size: 6 + Math.random() * 6,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    round: Math.random() < 0.45,
                });
            }
        };

        burst(90, window.innerWidth * 0.3);
        burst(90, window.innerWidth * 0.7);
        const second = window.setTimeout(() => burst(70, window.innerWidth * 0.5), 280);

        const start = performance.now();
        let raf = 0;
        const tick = (t: number) => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (const p of parts) {
                p.vy += 0.3;
                p.vx *= 0.99;
                p.x += p.vx;
                p.y += p.vy;
                p.rot += p.vr;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;
                if (p.round) {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                }
                ctx.restore();
            }
            if (t - start < duration) {
                raf = requestAnimationFrame(tick);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        };
        raf = requestAnimationFrame(tick);

        window.addEventListener('resize', resize);
        return () => {
            cancelAnimationFrame(raf);
            window.clearTimeout(second);
            window.removeEventListener('resize', resize);
        };
    }, [duration]);

    return <canvas ref={ref} className="pointer-events-none fixed inset-0 z-[200]" aria-hidden="true" />;
}
