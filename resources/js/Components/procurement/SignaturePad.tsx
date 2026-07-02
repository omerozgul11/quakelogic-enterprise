import { useEffect, useRef, useState } from 'react';
import { Eraser } from 'lucide-react';

interface Props {
    /** Called with a PNG data URL while there's ink, or null when cleared/empty. */
    onChange: (dataUrl: string | null) => void;
}

/**
 * A small canvas signature pad. Captures pointer strokes and emits a PNG data
 * URL for the approval step; the whole image is sent as base64 to the server.
 */
export function SignaturePad({ onChange }: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const dirty = useRef(false);
    const [hasInk, setHasInk] = useState(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        // Scale for crisp lines on HiDPI while keeping a fixed CSS size.
        const ratio = window.devicePixelRatio || 1;
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#1f2433';
        }
    }, []);

    const pos = (e: React.PointerEvent) => {
        const r = canvasRef.current!.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const start = (e: React.PointerEvent) => {
        drawing.current = true;
        const ctx = canvasRef.current!.getContext('2d')!;
        const { x, y } = pos(e);
        ctx.beginPath();
        ctx.moveTo(x, y);
        (e.target as Element).setPointerCapture?.(e.pointerId);
    };
    const move = (e: React.PointerEvent) => {
        if (!drawing.current) return;
        const ctx = canvasRef.current!.getContext('2d')!;
        const { x, y } = pos(e);
        ctx.lineTo(x, y);
        ctx.stroke();
        if (!dirty.current) { dirty.current = true; setHasInk(true); }
    };
    const end = () => {
        if (!drawing.current) return;
        drawing.current = false;
        if (dirty.current) onChange(canvasRef.current!.toDataURL('image/png'));
    };
    const clear = () => {
        const canvas = canvasRef.current!;
        canvas.getContext('2d')!.clearRect(0, 0, canvas.width, canvas.height);
        dirty.current = false;
        setHasInk(false);
        onChange(null);
    };

    return (
        <div>
            <div className="relative overflow-hidden rounded-lg border border-border bg-secondary/30">
                <canvas
                    ref={canvasRef}
                    className="h-32 w-full touch-none"
                    onPointerDown={start}
                    onPointerMove={move}
                    onPointerUp={end}
                    onPointerLeave={end}
                />
                {!hasInk && <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-muted-foreground">Sign here</span>}
            </div>
            <button type="button" onClick={clear} className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <Eraser className="h-3 w-3" /> Clear
            </button>
        </div>
    );
}
