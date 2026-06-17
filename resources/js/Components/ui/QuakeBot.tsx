/**
 * QuakeBot — the mascot. A pixel-art "Q" creature (matches QuakeLogic) in soft,
 * earthy pastel-terracotta tones, with a little seismic tail. Rendered as crisp
 * run-length-merged <rect>s. Exports the sprite data + renderer reused by the
 * animated <QuakeBotScene />.
 */

// Convert a string grid (one char per pixel) into crisp <rect>s, merging
// horizontal runs of the same color so we emit far fewer nodes.
export function renderPixels(rows: string[], palette: Record<string, string>, keyPrefix = 'p'): JSX.Element[] {
    const rects: JSX.Element[] = [];
    rows.forEach((row, y) => {
        let x = 0;
        while (x < row.length) {
            const ch = row[x];
            const fill = palette[ch];
            if (!fill || fill === 'none') { x++; continue; }
            let w = 1;
            while (x + w < row.length && row[x + w] === ch) w++;
            rects.push(<rect key={`${keyPrefix}-${x}-${y}`} x={x} y={y} width={w} height={1} fill={fill} />);
            x += w;
        }
    });
    return rects;
}

export const BOT_PAL: Record<string, string> = {
    '.': 'none',
    o: '#d98c66', // body (pastel terracotta)
    l: '#edb592', // soft highlight
    d: '#bd7549', // lower shade
    t: '#a85f3e', // seismic Q-tail
};

export const EYE_PAL: Record<string, string> = {
    '.': 'none',
    e: '#3a2316', // pupil
    W: '#ffffff', // glint
};

// 16×16 "Q" body (round head + a little tail at the lower-right). No eyes —
// eyes are a separate layer so the scene can blink/look.
export const BODY_SPRITE = [
    '................',
    '.....llllll.....',
    '...llllllllll...',
    '..llllllllllll..',
    '.oooooooooooooo.',
    '.oooooooooooooo.',
    'oooooooooooooooo',
    'oooooooooooooooo',
    'oooooooooooooooo',
    'oooooooooooooooo',
    '.oooooooooooooo.',
    '..oooooooooooo..',
    '..ooooooootto..',
    '...oooooooott..',
    '.....oooooo..tt.',
    '..............tt',
];

// Eyes layer (aligns to BODY_SPRITE grid).
export const EYES_SPRITE = [
    '................',
    '................',
    '................',
    '................',
    '................',
    '................',
    '................',
    '....We....We....',
    '....ee....ee....',
    '................',
    '................',
    '................',
    '................',
    '................',
    '................',
    '................',
];

export function QuakeBot({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 16 16"
            className={className}
            shapeRendering="crispEdges"
            style={{ imageRendering: 'pixelated' }}
            role="img"
            aria-label="QuakeBot"
        >
            {renderPixels(BODY_SPRITE, BOT_PAL, 'b')}
            {renderPixels(EYES_SPRITE, EYE_PAL, 'e')}
        </svg>
    );
}
