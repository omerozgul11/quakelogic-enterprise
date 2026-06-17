import { useEffect } from 'react';
import { X, ExternalLink, Download, FileText } from 'lucide-react';

export interface PreviewFile {
    name: string;
    mimeType: string;
    previewUrl: string;
    downloadUrl: string;
}

export function FilePreviewModal({ file, onClose }: { file: PreviewFile | null; onClose: () => void }) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        if (file) document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [file, onClose]);

    if (!file) return null;

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-8">
            <div className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={onClose} />
            <div className="animate-scale-in relative flex h-full max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                <div className="flex items-center gap-3 border-b border-border px-4 py-3">
                    <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <p className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">{file.name}</p>
                    <a href={file.previewUrl} target="_blank" rel="noreferrer" title="Open in new tab"
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                        <ExternalLink className="h-4 w-4" />
                    </a>
                    <a href={file.downloadUrl} title="Download"
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                        <Download className="h-4 w-4" />
                    </a>
                    <button onClick={onClose} title="Close"
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="flex-1 overflow-hidden bg-secondary/30">
                    <iframe title={file.name} src={file.previewUrl} className="h-full w-full border-0 bg-white" />
                </div>
            </div>
        </div>
    );
}
