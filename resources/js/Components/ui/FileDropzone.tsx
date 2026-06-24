import { useRef, useState } from 'react';
import { UploadCloud, X, FileText } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface Props {
    files: File[];
    onChange: (files: File[]) => void;
    accept?: string;
    multiple?: boolean;
    disabled?: boolean;
    hint?: string;
}

function fmtSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

/** Drag-and-drop (or click-to-browse) multi-file picker. Controlled via files/onChange. */
export function FileDropzone({ files, onChange, accept = '.pdf,.jpg,.jpeg,.png', multiple = true, disabled = false, hint }: Props) {
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const addFiles = (list: FileList | null) => {
        if (!list || list.length === 0) return;
        const incoming = Array.from(list);
        onChange(multiple ? [...files, ...incoming] : incoming.slice(0, 1));
    };

    const removeAt = (i: number) => onChange(files.filter((_, idx) => idx !== i));

    return (
        <div>
            <div
                onDragOver={e => { e.preventDefault(); if (!disabled) setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={e => { e.preventDefault(); setDragging(false); if (!disabled) addFiles(e.dataTransfer.files); }}
                onClick={() => !disabled && inputRef.current?.click()}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors',
                    dragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-secondary/40',
                    disabled && 'cursor-not-allowed opacity-60',
                )}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    className="hidden"
                    onChange={e => { addFiles(e.target.files); if (inputRef.current) inputRef.current.value = ''; }}
                />
                <span className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-brand-gradient">
                    <UploadCloud className="h-5 w-5 text-white" />
                </span>
                <p className="text-sm font-medium text-foreground">
                    Drop files here, or <span className="text-primary">browse</span>
                </p>
                <p className="mt-0.5 text-xs text-muted-foreground">{hint ?? 'PDF or image (JPEG/PNG), up to 100 MB each. Multiple files allowed.'}</p>
            </div>

            {files.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {files.map((f, i) => (
                        <li key={`${f.name}-${i}`} className="flex items-center gap-2 rounded-lg border border-border bg-secondary/40 px-3 py-2 text-sm">
                            <FileText className="h-4 w-4 shrink-0 text-primary" />
                            <span className="flex-1 truncate text-foreground">{f.name}</span>
                            <span className="shrink-0 text-xs text-muted-foreground">{fmtSize(f.size)}</span>
                            <button type="button" onClick={e => { e.stopPropagation(); removeAt(i); }} className="text-muted-foreground transition-colors hover:text-destructive">
                                <X className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
