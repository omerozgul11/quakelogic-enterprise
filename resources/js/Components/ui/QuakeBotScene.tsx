import { useEffect, useState } from 'react';
import { renderPixels, BODY_SPRITE, BOT_PAL, EYES_SPRITE, EYE_PAL } from '@/Components/ui/QuakeBot';

/**
 * Animated pixel-art QuakeBot at its desk. Loops through three scenes — the
 * laptop flips open and it types (live seismograph on screen), it reads while a
 * page flips, then it signs off — with a hop between each, blinking eyes, and an
 * occasional earthquake tremor (it's a seismic-systems company, after all).
 * Self-contained: scoped <style>, integer-grid sprites, crisp scaling.
 */

const PAL: Record<string, string> = {
    '.': 'none',
    o: '#d98c66',                         // body (for hands / eyelids)
    P: '#f4efe5', i: '#b9c0ca', s: '#00000022', // paper, ruled lines, soft shadow
    c: '#3a3f4b', S: '#21364f', q: '#7fd4e6', K: '#59636f', // laptop casing, screen, seismic trace, keys
    Y: '#e6b35a', n: '#d7d9e0', T: '#2e2113', // pen body, ferrule, nib
};

const KEYBOARD = [
    'cccccccccccc',
    'cKcKcKcKcKcc',
    '.ssssssssss.',
];

const SCREEN = [
    '.cccccccccc.',
    '.cSSSSSSSSc.',
    '.cSSSSSSSSc.',
    '.cSSSSSSSSc.',
    '.cccccccccc.',
];

const DOC = [
    'PPPPPPPPPPPP',
    'PiiiiiiiiiiP',
    'PPPPPPPPPPPP',
    'PiiiiiiiiiiP',
    'PPPPPPPPPPPP',
    '.ssssssssss.',
];

const DOC_FRONT = [
    'PPPPPPPPPPPP',
    'PiiiiiiPPPPP',
    'PPPPPPPPPPPP',
    'PPiiiiiiiiPP',
    'PPPPPPPPPPPP',
    '.ssssssssss.',
];

const PAPER = [
    'PPPPPPPPPPPP',
    'PPPPPPPPPPPP',
    'PPiiiiiiiiPP',
    '.ssssssssss.',
];

const PEN = [
    'Y.....',
    '.Y....',
    '..Y...',
    '...n..',
    '....n.',
    '.....T',
];

// Two seismograph frames (alternated) inside the laptop screen.
const WAVE_A: Array<[number, number]> = [[16, 12], [17, 11], [18, 12], [19, 13], [20, 12], [21, 11], [22, 12], [23, 13]];
const WAVE_B: Array<[number, number]> = [[16, 12], [17, 13], [18, 12], [19, 11], [20, 12], [21, 13], [22, 12], [23, 11]];

const STYLE = `
.qbs-bob  { animation: qbs-bob 2.6s ease-in-out infinite alternate; }
.qbs-hop  { animation: qbs-hop 0.6s ease-out 1; }
.qbs-pop  { animation: qbs-pop 0.45s ease-out 1; }
.qbs-tap1 { animation: qbs-tap 0.5s ease-in-out infinite; }
.qbs-tap2 { animation: qbs-tap 0.5s ease-in-out infinite; animation-delay: 0.25s; }
.qbs-read { animation: qbs-read 2.2s ease-in-out infinite alternate; }
.qbs-sign { animation: qbs-sign 0.7s ease-in-out infinite; }
.qbs-blink{ animation: qbs-blink 3.6s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
.qbs-look { animation: qbs-look 5s ease-in-out infinite; }
.qbs-open { animation: qbs-open 0.55s ease-out 1; transform-box: fill-box; transform-origin: center bottom; }
.qbs-flip { animation: qbs-flip 1.8s ease-in-out infinite; transform-box: fill-box; transform-origin: left center; }
.qbs-wA   { animation: qbs-wA 0.8s steps(1) infinite; }
.qbs-wB   { animation: qbs-wB 0.8s steps(1) infinite; }
.qbs-tremor { animation: qbs-tremor 6s ease-in-out infinite; }
@keyframes qbs-bob  { from { transform: translateY(0); } to { transform: translateY(-0.6px); } }
@keyframes qbs-hop  { 0%{transform:translateY(0)} 30%{transform:translateY(-2.2px)} 55%{transform:translateY(0)} 72%{transform:translateY(-0.8px)} 100%{transform:translateY(0)} }
@keyframes qbs-pop  { from { opacity:0; transform: translateY(2px); } to { opacity:1; transform: translateY(0); } }
@keyframes qbs-tap  { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-1px)} }
@keyframes qbs-read { from{transform:translateY(0)} to{transform:translateY(-1px)} }
@keyframes qbs-sign { 0%{transform:translateX(-1.6px)} 50%{transform:translateX(1.6px)} 100%{transform:translateX(-1.6px)} }
@keyframes qbs-blink{ 0%,92%,100%{transform:scaleY(1)} 96%{transform:scaleY(0.12)} }
@keyframes qbs-look { 0%,30%{transform:translateX(0)} 45%,65%{transform:translateX(0.7px)} 80%,100%{transform:translateX(0)} }
@keyframes qbs-open { 0%{transform:scaleY(0)} 70%{transform:scaleY(1.06)} 100%{transform:scaleY(1)} }
@keyframes qbs-flip { 0%,45%{transform:scaleX(1)} 60%{transform:scaleX(0.06)} 75%,100%{transform:scaleX(1)} }
@keyframes qbs-wA   { 0%,49%{opacity:1} 50%,100%{opacity:0} }
@keyframes qbs-wB   { 0%,49%{opacity:0} 50%,100%{opacity:1} }
@keyframes qbs-tremor { 0%,84%,100%{transform:translate(0,0)} 86%{transform:translate(0.5px,-0.2px)} 88%{transform:translate(-0.5px,0.2px)} 90%{transform:translate(0.4px,0.1px)} 92%{transform:translate(-0.3px,-0.1px)} 94%{transform:translate(0,0)} }
@media (prefers-reduced-motion: reduce){
  .qbs-bob,.qbs-hop,.qbs-pop,.qbs-tap1,.qbs-tap2,.qbs-read,.qbs-sign,.qbs-blink,.qbs-look,.qbs-open,.qbs-flip,.qbs-wA,.qbs-wB,.qbs-tremor{animation:none!important}
}
`;

function Wave({ pts, cls }: { pts: Array<[number, number]>; cls: string }) {
    return <g className={cls}>{pts.map(([x, y]) => <rect key={`${x}-${y}`} x={x} y={y} width={1} height={1} fill={PAL.q} />)}</g>;
}

export function QuakeBotScene() {
    const [scene, setScene] = useState(0);

    useEffect(() => {
        const id = setInterval(() => setScene(s => (s + 1) % 3), 3000);
        return () => clearInterval(id);
    }, []);

    return (
        <div className="flex items-center justify-center">
            <style>{STYLE}</style>
            <svg
                viewBox="0 0 40 26"
                className="h-20 w-auto"
                shapeRendering="crispEdges"
                style={{ imageRendering: 'pixelated' }}
                role="img"
                aria-label="QuakeBot working on your proposals"
            >
                <g className="qbs-tremor">
                    {/* creature — hop on scene change, idle bob, blinking + glancing eyes */}
                    <g key={scene} className="qbs-hop">
                        <g className="qbs-bob">
                            <g transform="translate(12 1)">{renderPixels(BODY_SPRITE, BOT_PAL, 'cr')}</g>
                            <g transform="translate(12 1)">
                                <g className="qbs-look">
                                    <g className="qbs-blink">{renderPixels(EYES_SPRITE, EYE_PAL, 'ey')}</g>
                                </g>
                            </g>
                        </g>
                    </g>

                    {/* desk */}
                    <rect x={3} y={16} width={34} height={1} fill="#8a6a49" />
                    <rect x={3} y={17} width={34} height={1} fill="#6f5236" />
                    <rect x={3} y={18} width={34} height={5} fill="#4e3826" />
                    <rect x={6} y={23} width={2} height={3} fill="#3c2c1d" />
                    <rect x={32} y={23} width={2} height={3} fill="#3c2c1d" />

                    {/* scene 0 — laptop flips open, seismograph runs, hands type */}
                    {scene === 0 && (
                        <g className="qbs-pop">
                            <g transform="translate(14 15)">{renderPixels(KEYBOARD, PAL, 'kb')}</g>
                            <g className="qbs-open">
                                <g transform="translate(14 10)">{renderPixels(SCREEN, PAL, 'sc')}</g>
                                <Wave pts={WAVE_A} cls="qbs-wA" />
                                <Wave pts={WAVE_B} cls="qbs-wB" />
                            </g>
                            <g className="qbs-tap1"><rect x={17} y={15} width={2} height={1} fill={PAL.o} /></g>
                            <g className="qbs-tap2"><rect x={21} y={15} width={2} height={1} fill={PAL.o} /></g>
                        </g>
                    )}

                    {/* scene 1 — reading, with a page flipping */}
                    {scene === 1 && (
                        <g className="qbs-pop">
                            <g className="qbs-read">
                                <g transform="translate(14 10)">{renderPixels(DOC, PAL, 'doc')}</g>
                                <g className="qbs-flip"><g transform="translate(14 10)">{renderPixels(DOC_FRONT, PAL, 'docf')}</g></g>
                                <rect x={14} y={15} width={2} height={1} fill={PAL.o} />
                                <rect x={23} y={15} width={2} height={1} fill={PAL.o} />
                            </g>
                        </g>
                    )}

                    {/* scene 2 — signing */}
                    {scene === 2 && (
                        <g className="qbs-pop">
                            <g transform="translate(14 13)">{renderPixels(PAPER, PAL, 'pap')}</g>
                            <g className="qbs-sign">
                                <g transform="translate(17 10)">{renderPixels(PEN, PAL, 'pen')}</g>
                                <rect x={16} y={11} width={3} height={1} fill={PAL.o} />
                            </g>
                        </g>
                    )}
                </g>
            </svg>
        </div>
    );
}
