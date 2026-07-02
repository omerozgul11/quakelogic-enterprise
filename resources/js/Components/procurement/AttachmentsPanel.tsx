import { useForm, router } from '@inertiajs/react';
import { useRef } from 'react';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Paperclip, Upload, Trash2, FileText } from 'lucide-react';

export interface Attachment {
    id: number;
    name: string;
    size: number | null;
    mime: string | null;
    uploaded_by: string | null;
    created_at: string | null;
    download_url: string;
}

interface Props {
    /** Route segment for the parent: purchase-requests | quotations | purchase-orders | bills */
    entity: string;
    id: number;
    attachments: Attachment[];
    canManage: boolean;
}

function humanSize(bytes: number | null): string {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let n = bytes;
    let u = 0;
    while (n >= 1024 && u < units.length - 1) { n /= 1024; u++; }
    return `${n.toFixed(n < 10 && u > 0 ? 1 : 0)} ${units[u]}`;
}

/**
 * Upload / list / download / delete file attachments on a procurement document.
 * Files are private (served through an authorized controller action).
 */
export function AttachmentsPanel({ entity, id, attachments, canManage }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const form = useForm<{ file: File | null }>({ file: null });

    const upload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        form.transform(() => ({ file }));
        form.post(`/procurement/attachments/${entity}/${id}`, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { if (inputRef.current) inputRef.current.value = ''; },
        });
    };

    const remove = (attachmentId: number) => {
        if (!confirm('Remove this attachment?')) return;
        router.delete(`/procurement/attachments/${attachmentId}`, { preserveScroll: true });
    };

    return (
        <Card className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-border px-5 py-3">
                <h2 className="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                    <Paperclip className="h-3.5 w-3.5" /> Attachments
                </h2>
                {canManage && (
                    <>
                        <input ref={inputRef} type="file" className="hidden" onChange={upload} />
                        <Button variant="secondary" size="sm" icon={Upload} disabled={form.processing} onClick={() => inputRef.current?.click()}>
                            {form.processing ? 'Uploading…' : 'Upload'}
                        </Button>
                    </>
                )}
            </div>
            {form.errors.file && <p className="px-5 pt-2 text-xs text-destructive">{form.errors.file}</p>}
            {attachments.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">No files attached.</p>
            ) : (
                <ul className="divide-y divide-border">
                    {attachments.map(a => (
                        <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                            <a href={a.download_url} className="flex min-w-0 items-center gap-2 text-sm text-foreground hover:text-primary" data-no-row-link>
                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <span className="truncate">{a.name}</span>
                            </a>
                            <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                                {a.size != null && <span>{humanSize(a.size)}</span>}
                                {a.uploaded_by && <span className="hidden sm:inline">{a.uploaded_by}</span>}
                                {canManage && (
                                    <button type="button" onClick={() => remove(a.id)} className="text-muted-foreground hover:text-destructive" title="Remove">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
