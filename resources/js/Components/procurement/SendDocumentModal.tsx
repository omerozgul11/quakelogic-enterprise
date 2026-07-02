import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Send, FileText } from 'lucide-react';

export interface SendMeta {
    recipients: { email: string; label: string }[];
    to: string;
    subject: string;
    message: string;
    pdf_url: string;
    send_url: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    meta: SendMeta;
    /** Lower-case noun for copy, e.g. "purchase order", "request for quotation". */
    kindLabel: string;
}

/**
 * Shared "Send to vendor" modal for procurement documents. Prefilled recipient
 * (with quick-pick suggestions from the supplier's contacts), CC/BCC, an
 * editable subject and message, and a link to preview the PDF that will be
 * attached. Posts to the document's own send-email endpoint.
 */
export function SendDocumentModal({ open, onClose, meta, kindLabel }: Props) {
    const form = useForm({
        to: meta.to ?? '',
        cc: '',
        bcc: '',
        subject: meta.subject ?? '',
        message: meta.message ?? '',
    });

    const submit = () => {
        form.post(meta.send_url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('cc', 'bcc');
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Send to vendor"
            description={`Email this ${kindLabel} with the PDF attached.`}
            size="lg"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={form.processing}>Cancel</Button>
                    <Button icon={Send} onClick={submit} disabled={form.processing || !form.data.to}>
                        {form.processing ? 'Sending…' : 'Send email'}
                    </Button>
                </>
            }
        >
            <div className="space-y-4">
                <div>
                    <label className="label">To *</label>
                    <input
                        className="input"
                        type="email"
                        placeholder="vendor@example.com"
                        value={form.data.to}
                        onChange={e => form.setData('to', e.target.value)}
                    />
                    {form.errors.to && <p className="mt-1 text-xs text-destructive">{form.errors.to}</p>}
                    {meta.recipients.length > 0 && (
                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                            <span className="text-xs text-muted-foreground">Suggested:</span>
                            {meta.recipients.map(r => (
                                <button
                                    key={r.email}
                                    type="button"
                                    onClick={() => form.setData('to', r.email)}
                                    className="chip hover:bg-secondary"
                                    title={r.email}
                                >
                                    {r.label || r.email}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="label">CC</label>
                        <input
                            className="input"
                            placeholder="comma-separated"
                            value={form.data.cc}
                            onChange={e => form.setData('cc', e.target.value)}
                        />
                        {form.errors.cc && <p className="mt-1 text-xs text-destructive">{form.errors.cc}</p>}
                    </div>
                    <div>
                        <label className="label">BCC</label>
                        <input
                            className="input"
                            placeholder="comma-separated"
                            value={form.data.bcc}
                            onChange={e => form.setData('bcc', e.target.value)}
                        />
                        {form.errors.bcc && <p className="mt-1 text-xs text-destructive">{form.errors.bcc}</p>}
                    </div>
                </div>

                <div>
                    <label className="label">Subject</label>
                    <input className="input" value={form.data.subject} onChange={e => form.setData('subject', e.target.value)} />
                    {form.errors.subject && <p className="mt-1 text-xs text-destructive">{form.errors.subject}</p>}
                </div>

                <div>
                    <label className="label">Message</label>
                    <textarea
                        className="input min-h-[120px]"
                        value={form.data.message}
                        onChange={e => form.setData('message', e.target.value)}
                    />
                    {form.errors.message && <p className="mt-1 text-xs text-destructive">{form.errors.message}</p>}
                </div>

                <a
                    href={meta.pdf_url}
                    target="_blank"
                    rel="noopener"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    <FileText className="h-4 w-4" /> Preview attached PDF
                </a>
            </div>
        </Modal>
    );
}
