/**
 * Minimalist QuakeAI mark — echoes the QuakeLogic logo (a ring of nodes around
 * a pulse) in a single monochrome glyph that sits next to the notification bell.
 * Uses currentColor so it inherits the header's icon styling.
 */
export function QuakeAiIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" fill="none" className={className} aria-hidden="true">
            <circle cx="12" cy="12" r="8.5" stroke="currentColor" strokeWidth="1.8" strokeOpacity="0.9" />
            {/* central pulse */}
            <path d="M8 12h2l1.3-3 2.4 6 1.3-3H17" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
