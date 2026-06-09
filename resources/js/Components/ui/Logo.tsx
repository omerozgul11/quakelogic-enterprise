import { cn } from '@/Lib/utils';

/**
 * QuakeLogic brand mark — a rounded tile with a seismic "signal" pulse.
 * `mono` renders a white tile with an orange signal, for use on the orange header.
 */
export function LogoMark({ className, size = 36, mono = false }: { className?: string; size?: number; mono?: boolean }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('shrink-0', className)}
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="ql-grad" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#FB8C3E" />
                    <stop offset="1" stopColor="#F26522" />
                </linearGradient>
            </defs>
            <rect x="2" y="2" width="44" height="44" rx="13" fill={mono ? '#ffffff' : 'url(#ql-grad)'} />
            {!mono && (
                <rect x="2.75" y="2.75" width="42.5" height="42.5" rx="12.25" stroke="white" strokeOpacity="0.18" strokeWidth="1.5" />
            )}
            <path
                d="M9 25.5H16.5L20 16L26 33L30 22L33 27.5H39"
                stroke={mono ? '#F26522' : 'white'}
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export function Logo({ className, dark = false }: { className?: string; dark?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <LogoMark size={34} />
            <span className="flex flex-col leading-none">
                <span className={cn('text-[17px] font-extrabold tracking-tight', dark ? 'text-white' : 'text-foreground')}>
                    QuakeLogic
                </span>
                <span className={cn('text-[10px] font-semibold uppercase tracking-[0.15em]', dark ? 'text-orange-100/90' : 'text-muted-foreground')}>
                    Enterprise
                </span>
            </span>
        </span>
    );
}
