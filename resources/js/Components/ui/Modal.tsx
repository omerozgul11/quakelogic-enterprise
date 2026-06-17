import { ReactNode, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, AlertTriangle } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Button } from '@/Components/ui/Button';

type Size = 'sm' | 'md' | 'lg' | 'xl';

const WIDTHS: Record<Size, string> = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    size?: Size;
}

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: ModalProps) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prev;
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-[120] flex items-start justify-center overflow-y-auto p-4 sm:p-6">
            <div className="fixed inset-0 animate-fade-in bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div
                role="dialog"
                aria-modal="true"
                className={cn('relative z-10 my-8 w-full animate-scale-in rounded-2xl border border-border bg-card shadow-xl', WIDTHS[size])}
            >
                {(title || description) && (
                    <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                        <div className="min-w-0">
                            {title && <h2 className="text-lg font-semibold text-foreground">{title}</h2>}
                            {description && <p className="mt-0.5 text-sm text-muted-foreground">{description}</p>}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1.5 text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>
                )}
                <div className="px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-border px-5 py-4">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}

interface ConfirmDialogProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title?: string;
    message: ReactNode;
    confirmLabel?: string;
    processing?: boolean;
}

export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title = 'Are you sure?',
    message,
    confirmLabel = 'Delete',
    processing = false,
}: ConfirmDialogProps) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button variant="danger" onClick={onConfirm} disabled={processing}>
                        {processing ? 'Deleting…' : confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="flex gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                    <AlertTriangle className="h-5 w-5 text-destructive" />
                </div>
                <div>
                    <h3 className="text-base font-semibold text-foreground">{title}</h3>
                    <div className="mt-1 text-sm text-muted-foreground">{message}</div>
                </div>
            </div>
        </Modal>
    );
}
