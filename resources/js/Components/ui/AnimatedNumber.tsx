import { useEffect, useRef, useState } from 'react';

interface Props {
    value: number;
    /** Render the (possibly fractional, mid-tween) value — defaults to a rounded, grouped integer. */
    format?: (n: number) => string;
    /** Tween duration in ms. */
    duration?: number;
    className?: string;
}

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

/**
 * Counts smoothly from its previous value to the new one whenever `value`
 * changes (e.g. when filters update the stat) — no animation on first paint, and
 * interruptions resume from wherever the tween currently is. Honors
 * prefers-reduced-motion.
 */
export function AnimatedNumber({ value, format, duration = 650, className }: Props) {
    const [display, setDisplay] = useState(value);
    const displayRef = useRef(value);
    const rafRef = useRef<number | null>(null);

    useEffect(() => {
        const from = displayRef.current;
        const to = value;
        if (from === to) return;

        const reduce = typeof window !== 'undefined'
            && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            displayRef.current = to;
            setDisplay(to);
            return;
        }

        let start = 0;
        const step = (ts: number) => {
            if (!start) start = ts;
            const t = Math.min(1, (ts - start) / duration);
            const current = from + (to - from) * easeOutCubic(t);
            displayRef.current = current;
            setDisplay(current);
            if (t < 1) {
                rafRef.current = requestAnimationFrame(step);
            } else {
                displayRef.current = to;
                setDisplay(to);
            }
        };
        rafRef.current = requestAnimationFrame(step);

        return () => { if (rafRef.current) cancelAnimationFrame(rafRef.current); };
    }, [value, duration]);

    const fmt = format ?? ((n: number) => Math.round(n).toLocaleString());
    return <span className={className}>{fmt(display)}</span>;
}
