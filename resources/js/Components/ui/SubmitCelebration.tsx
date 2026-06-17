import { Confetti } from './Confetti';
import { PartyPopper } from 'lucide-react';

/**
 * A simple, engaging "proposal submitted" celebration: a centered card with a
 * confetti burst behind it. Shown once, right after a proposal is submitted.
 */
export function SubmitCelebration({ proposalNumber, onClose }: { proposalNumber?: string | null; onClose: () => void }) {
    return (
        <div className="fixed inset-0 z-[190] flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
            <Confetti />
            <div className="relative z-[195] w-full max-w-md animate-scale-in rounded-2xl border border-border bg-card p-8 text-center shadow-2xl">
                <div className="bg-brand-gradient shadow-glow mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl">
                    <PartyPopper className="h-8 w-8 text-white" />
                </div>
                <h2 className="text-2xl font-extrabold tracking-tight text-foreground">Proposal submitted! 🎉</h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Nice work{proposalNumber ? <> — <span className="font-semibold text-foreground">{proposalNumber}</span> is on its way</> : ''}.
                    It's now marked as submitted in your pipeline.
                </p>
                <button
                    onClick={onClose}
                    className="bg-brand-gradient shadow-glow mt-6 w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-95 active:scale-[0.99]"
                >
                    Done
                </button>
            </div>
        </div>
    );
}
