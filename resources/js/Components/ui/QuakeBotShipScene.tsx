import { useEffect, useState } from 'react';
import { renderPixels, BODY_SPRITE, BOT_PAL, EYES_SPRITE, EYE_PAL } from '@/Components/ui/QuakeBot';

/**
 * Animated pixel-art QuakeBot for the Shipments app. The mascot stands on the
 * left (idle bob, blinking + glancing eyes) while the stage on the right cycles
 * through three delivery vignettes every 3s: a parcel truck driving (spinning
 * wheels, scrolling road, speed lines), shipments being loaded onto a pallet
 * (boxes stacking in, progress bar), and a cargo ship sailing (rocking on
 * animated waves, waving flag, rising smoke). Self-contained: scoped <style>,
 * integer-grid art, crisp scaling, and it stills itself for reduced-motion.
 */

const C = {
    // ground / road
    road: '#3a3f4b', roadHi: '#4a515e', dash: '#e6b35a', floor: '#454b57', floorHi: '#555c69',
    motion: '#cbd2da',
    // truck
    truck: '#b5723f', truckHi: '#cf8a52', truckSh: '#8a5530', glass: '#bfe0ef',
    label: '#f4efe5', labelLine: '#b9c0ca', tire: '#2b2f38', hub: '#d7d9e0',
    // boxes / pallet
    box: '#caa06a', boxHi: '#dcb888', boxSh: '#a9763f', tape: '#8a6a49',
    pallet: '#6f5236', palletSh: '#54402a', palletHi: '#8a6a49', barTrack: '#2b2f38',
    // ship / water
    water: '#3f9ec2', waterHi: '#67b8d8', waterSh: '#2f7f9e',
    hull: '#a23b30', hullSh: '#7d2f26', deck: '#caa46a',
    cabin: '#f4efe5', cabinWin: '#7fb4cb', mast: '#6f5236', flag: '#d98c66', smoke: '#cfd6dd',
    containerA: '#3f7fb0', containerAHi: '#5b9ccc', containerB: '#4f9e6a',
};

const STYLE = `
.qss-bob   { animation: qss-bob 2.6s ease-in-out infinite alternate; }
.qss-blink { animation: qss-blink 3.6s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
.qss-look  { animation: qss-look 5s ease-in-out infinite; }
.qss-pop   { animation: qss-pop 0.5s ease-out 1; }
.qss-drive { animation: qss-drive 0.5s ease-in-out infinite; }
.qss-wheel { transform-box: fill-box; transform-origin: center; animation: qss-wheel 0.7s linear infinite; }
.qss-road  { animation: qss-road 0.5s linear infinite; }
.qss-speed { animation: qss-speed 0.5s ease-in-out infinite; }
.qss-stack1{ transform-box: fill-box; animation: qss-stack 0.5s ease-out both; animation-delay: 0.15s; }
.qss-stack2{ transform-box: fill-box; animation: qss-stack 0.5s ease-out both; animation-delay: 0.4s; }
.qss-stack3{ transform-box: fill-box; animation: qss-stack 0.5s ease-out both; animation-delay: 0.7s; }
.qss-place { animation: qss-place 1.4s ease-in-out infinite; animation-delay: 1.2s; }
.qss-load  { transform-box: fill-box; transform-origin: left center; animation: qss-load 2.4s ease-in-out infinite; }
.qss-rock  { transform-box: fill-box; transform-origin: 50% 92%; animation: qss-rock 3.2s ease-in-out infinite; }
.qss-wave  { animation: qss-wave 1.5s linear infinite; }
.qss-wave2 { animation: qss-wave 2.4s linear infinite; }
.qss-flag  { transform-box: fill-box; transform-origin: left center; animation: qss-flag 0.9s ease-in-out infinite; }
.qss-smoke1{ animation: qss-smoke 1.8s ease-out infinite; }
.qss-smoke2{ animation: qss-smoke 1.8s ease-out infinite; animation-delay: 0.9s; }
@keyframes qss-bob   { from { transform: translateY(0); } to { transform: translateY(-0.7px); } }
@keyframes qss-blink { 0%,92%,100%{transform:scaleY(1)} 96%{transform:scaleY(0.12)} }
@keyframes qss-look  { 0%,30%{transform:translateX(0)} 45%,70%{transform:translateX(0.8px)} 85%,100%{transform:translateX(0)} }
@keyframes qss-pop   { from { opacity:0; transform: translateY(2px); } to { opacity:1; transform: translateY(0); } }
@keyframes qss-drive { 0%,100%{transform:translateY(0)} 25%{transform:translateY(-0.4px)} 60%{transform:translateY(-0.7px)} }
@keyframes qss-wheel { from { transform: rotate(0); } to { transform: rotate(360deg); } }
@keyframes qss-road  { from { transform: translateX(0); } to { transform: translateX(-8px); } }
@keyframes qss-speed { 0%,100%{opacity:0.15; transform:translateX(0)} 50%{opacity:0.6; transform:translateX(-1.5px)} }
@keyframes qss-stack { 0%{opacity:0; transform:translateY(-7px)} 70%{opacity:1; transform:translateY(0.5px)} 100%{opacity:1; transform:translateY(0)} }
@keyframes qss-place { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-0.8px)} }
@keyframes qss-load  { 0%{transform:scaleX(0.05)} 70%{transform:scaleX(1)} 100%{transform:scaleX(1); opacity:0.5} }
@keyframes qss-rock  { 0%,100%{transform:rotate(-2.2deg)} 50%{transform:rotate(2.2deg)} }
@keyframes qss-wave  { from { transform: translateX(0); } to { transform: translateX(-8px); } }
@keyframes qss-flag  { 0%,100%{transform:skewX(0) scaleX(1)} 50%{transform:skewX(-10deg) scaleX(0.9)} }
@keyframes qss-smoke { 0%{opacity:0; transform:translate(0,0) scale(0.5)} 25%{opacity:0.7} 100%{opacity:0; transform:translate(-1.5px,-6px) scale(1.3)} }
@media (prefers-reduced-motion: reduce){
  .qss-bob,.qss-blink,.qss-look,.qss-pop,.qss-drive,.qss-wheel,.qss-road,.qss-speed,
  .qss-stack1,.qss-stack2,.qss-stack3,.qss-place,.qss-load,.qss-rock,.qss-wave,.qss-wave2,
  .qss-flag,.qss-smoke1,.qss-smoke2 { animation: none !important; }
}
`;

function Wheel({ cx, cy, r = 2.4 }: { cx: number; cy: number; r?: number }) {
    return (
        <g transform={`translate(${cx} ${cy})`}>
            <g className="qss-wheel">
                <circle r={r} fill={C.tire} />
                <circle r={r * 0.45} fill={C.hub} />
                <rect x={-r} y={-0.4} width={r * 2} height={0.8} fill={C.tire} />
                <rect x={-0.4} y={-r} width={0.8} height={r * 2} fill={C.tire} />
            </g>
        </g>
    );
}

function Box({ x, y }: { x: number; y: number }) {
    return (
        <g transform={`translate(${x} ${y})`}>
            <rect x={0} y={0} width={8} height={6} fill={C.box} />
            <rect x={0} y={0} width={8} height={1} fill={C.boxHi} />
            <rect x={0} y={5} width={8} height={1} fill={C.boxSh} />
            <rect x={3} y={0} width={2} height={6} fill={C.tape} opacity={0.85} />
            <rect x={0} y={2} width={8} height={1} fill={C.tape} opacity={0.85} />
        </g>
    );
}

export function QuakeBotShipScene() {
    const [scene, setScene] = useState(0);

    useEffect(() => {
        const id = setInterval(() => setScene(s => (s + 1) % 3), 3000);
        return () => clearInterval(id);
    }, []);

    return (
        <div className="flex items-center justify-center">
            <style>{STYLE}</style>
            <svg
                viewBox="0 0 52 28"
                className="h-20 w-auto"
                shapeRendering="crispEdges"
                style={{ imageRendering: 'pixelated' }}
                role="img"
                aria-label="QuakeBot tracking your shipments"
            >
                <defs>
                    <clipPath id="qss-road-clip"><rect x={18} y={22} width={34} height={6} /></clipPath>
                    <clipPath id="qss-water-clip"><rect x={17} y={19} width={35} height={9} /></clipPath>
                </defs>

                {/* scene 0 — parcel truck driving */}
                {scene === 0 && (
                    <g key="truck" className="qss-pop">
                        <rect x={0} y={22} width={52} height={6} fill={C.road} />
                        <rect x={0} y={22} width={52} height={1} fill={C.roadHi} />
                        <g clipPath="url(#qss-road-clip)">
                            <g className="qss-road">
                                {[0, 8, 16, 24, 32, 40].map(dx => (
                                    <rect key={dx} x={18 + dx} y={25} width={4} height={1} fill={C.dash} />
                                ))}
                            </g>
                        </g>
                        <g className="qss-speed">
                            <rect x={19} y={16} width={3} height={1} fill={C.motion} />
                            <rect x={19} y={19} width={4} height={1} fill={C.motion} />
                        </g>
                        <g className="qss-drive">
                            <g transform="translate(20 0)">
                                <rect x={0} y={11} width={16} height={9} fill={C.truck} />
                                <rect x={0} y={11} width={16} height={1} fill={C.truckHi} />
                                <rect x={0} y={19} width={16} height={1} fill={C.truckSh} />
                                <rect x={6} y={11} width={1} height={9} fill={C.truckSh} opacity={0.5} />
                                <rect x={11} y={11} width={1} height={9} fill={C.truckSh} opacity={0.5} />
                                <rect x={2} y={14} width={6} height={4} fill={C.label} />
                                <rect x={3} y={15} width={4} height={1} fill={C.labelLine} />
                                <rect x={3} y={16} width={3} height={1} fill={C.labelLine} />
                                <rect x={16} y={14} width={7} height={6} fill={C.truck} />
                                <rect x={16} y={14} width={7} height={1} fill={C.truckHi} />
                                <rect x={17} y={15} width={4} height={2} fill={C.glass} />
                                <rect x={23} y={17} width={2} height={3} fill={C.truck} />
                                <rect x={23} y={19} width={3} height={1} fill={C.truckSh} />
                                <rect x={24} y={18} width={1} height={1} fill={C.label} />
                                <Wheel cx={5} cy={21} />
                                <Wheel cx={19} cy={21} />
                            </g>
                        </g>
                    </g>
                )}

                {/* scene 1 — loading shipments onto a pallet */}
                {scene === 1 && (
                    <g key="load" className="qss-pop">
                        <rect x={0} y={22} width={52} height={6} fill={C.floor} />
                        <rect x={0} y={22} width={52} height={1} fill={C.floorHi} />
                        <rect x={26} y={20} width={22} height={2} fill={C.pallet} />
                        <rect x={28} y={20} width={1} height={2} fill={C.palletSh} />
                        <rect x={36} y={20} width={1} height={2} fill={C.palletSh} />
                        <rect x={44} y={20} width={1} height={2} fill={C.palletSh} />
                        <g className="qss-stack1"><Box x={28} y={14} /></g>
                        <g className="qss-stack2"><Box x={37} y={14} /></g>
                        <g className="qss-stack3"><g className="qss-place"><Box x={32} y={8} /></g></g>
                        <rect x={28} y={25} width={17} height={1} fill={C.barTrack} />
                        <rect x={28} y={25} width={17} height={1} fill={C.flag} className="qss-load" />
                    </g>
                )}

                {/* scene 2 — cargo ship sailing */}
                {scene === 2 && (
                    <g key="ship" className="qss-pop">
                        <rect x={0} y={20} width={52} height={8} fill={C.water} />
                        <rect x={0} y={21} width={17} height={3} fill={C.pallet} />
                        <rect x={0} y={21} width={17} height={1} fill={C.palletHi} />
                        <g clipPath="url(#qss-water-clip)">
                            <g className="qss-wave">
                                {[0, 8, 16, 24, 32].map(dx => (
                                    <rect key={dx} x={17 + dx} y={20} width={4} height={1} fill={C.waterHi} />
                                ))}
                            </g>
                            <g className="qss-wave2">
                                {[0, 8, 16, 24, 32].map(dx => (
                                    <rect key={`b${dx}`} x={13 + dx} y={23} width={3} height={1} fill={C.waterSh} />
                                ))}
                            </g>
                        </g>
                        <g className="qss-rock">
                            <g transform="translate(21 4)">
                                <rect x={6} y={0} width={1} height={10} fill={C.mast} />
                                <g className="qss-flag"><polygon points="7,0 13,2 7,4" fill={C.flag} /></g>
                                <rect x={17} y={6} width={2} height={4} fill={C.hull} />
                                <rect x={17} y={7} width={2} height={1} fill={C.cabin} />
                                <circle className="qss-smoke1" cx={18} cy={5} r={1.2} fill={C.smoke} />
                                <circle className="qss-smoke2" cx={18} cy={5} r={1} fill={C.smoke} />
                                <rect x={8} y={8} width={9} height={6} fill={C.cabin} />
                                <rect x={8} y={8} width={9} height={1} fill="#ffffff" />
                                <rect x={9} y={10} width={1} height={1} fill={C.cabinWin} />
                                <rect x={11} y={10} width={1} height={1} fill={C.cabinWin} />
                                <rect x={13} y={10} width={1} height={1} fill={C.cabinWin} />
                                <rect x={2} y={11} width={5} height={3} fill={C.containerA} />
                                <rect x={2} y={11} width={5} height={1} fill={C.containerAHi} />
                                <rect x={19} y={11} width={4} height={3} fill={C.containerB} />
                                <rect x={0} y={14} width={26} height={1} fill={C.deck} />
                                <rect x={0} y={15} width={26} height={3} fill={C.hull} />
                                <rect x={3} y={18} width={20} height={1} fill={C.hull} />
                                <rect x={6} y={19} width={14} height={1} fill={C.hullSh} />
                                <circle cx={5} cy={16} r={0.8} fill={C.deck} />
                                <circle cx={13} cy={16} r={0.8} fill={C.deck} />
                                <circle cx={21} cy={16} r={0.8} fill={C.deck} />
                            </g>
                        </g>
                    </g>
                )}

                {/* QuakeBot — constant host on the left: idle bob, blinking + glancing eyes */}
                <ellipse cx={10} cy={22} rx={7} ry={1.2} fill="#000000" opacity={0.12} />
                <g className="qss-bob">
                    <g transform="translate(2 6)">{renderPixels(BODY_SPRITE, BOT_PAL, 'b')}</g>
                    <g transform="translate(2 6)">
                        <g className="qss-look">
                            <g className="qss-blink">{renderPixels(EYES_SPRITE, EYE_PAL, 'e')}</g>
                        </g>
                    </g>
                </g>
            </svg>
        </div>
    );
}

const LOADER_STYLE = `
.qdl-drive { animation: qdl-drive 0.5s ease-in-out infinite; }
.qdl-wheel { transform-box: fill-box; transform-origin: center; animation: qdl-wheel 0.6s linear infinite; }
.qdl-speed { animation: qdl-speed 0.5s ease-in-out infinite; }
@keyframes qdl-drive { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-0.6px)} }
@keyframes qdl-wheel { from { transform: rotate(0); } to { transform: rotate(360deg); } }
@keyframes qdl-speed { 0%,100%{opacity:0.2; transform:translateX(0)} 50%{opacity:0.6; transform:translateX(-1.5px)} }
@media (prefers-reduced-motion: reduce){ .qdl-drive,.qdl-wheel,.qdl-speed { animation: none !important; } }
`;

function MiniWheel({ cx }: { cx: number }) {
    return (
        <g transform={`translate(${cx} 10)`}>
            <g className="qdl-wheel">
                <circle r={2} fill="#2b2f38" />
                <circle r={0.9} fill="#d7d9e0" />
                <rect x={-1.8} y={-0.4} width={3.6} height={0.8} fill="#2b2f38" />
                <rect x={-0.4} y={-1.8} width={0.8} height={3.6} fill="#2b2f38" />
            </g>
        </g>
    );
}

/**
 * Compact "out for delivery" loader — a parcel truck driving with spinning
 * wheels and speed lines. Used as the chat's thinking/sending indicator.
 */
export function DeliveryLoader() {
    return (
        <span className="inline-flex items-center" aria-label="Delivering…">
            <style>{LOADER_STYLE}</style>
            <svg viewBox="0 0 30 14" className="h-6 w-auto" shapeRendering="crispEdges" style={{ imageRendering: 'pixelated' }}>
                <g className="qdl-speed">
                    <rect x={1} y={4} width={3} height={1} fill="#cbd2da" />
                    <rect x={1} y={7} width={4} height={1} fill="#cbd2da" />
                </g>
                <g className="qdl-drive">
                    <rect x={6} y={2} width={11} height={8} fill="#b5723f" />
                    <rect x={6} y={2} width={11} height={1} fill="#cf8a52" />
                    <rect x={8} y={4} width={5} height={3} fill="#f4efe5" />
                    <rect x={17} y={4} width={6} height={6} fill="#b5723f" />
                    <rect x={18} y={5} width={3} height={2} fill="#bfe0ef" />
                    <rect x={23} y={7} width={2} height={3} fill="#b5723f" />
                    <MiniWheel cx={10} />
                    <MiniWheel cx={20} />
                </g>
            </svg>
        </span>
    );
}
