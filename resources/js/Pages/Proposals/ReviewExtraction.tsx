import { Head, useForm, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Sparkles, FileText, Check, ArrowRight } from 'lucide-react';
import { useState } from 'react';

interface FieldChange {
    key: string;
    label: string;
    current: string | null;
    extracted: string | null;
    changed: boolean;
}

interface RecordChange {
    key: 'agency' | 'company' | 'contact' | 'follow_up';
    label: string;
    value: string;
    action: 'create' | 'link';
}

interface Props {
    proposal: { id: number; proposal_number: string; project_name: string };
    changes: { fields: FieldChange[]; records: RecordChange[] };
    confidence: number | null;
    provider: string;
    file: string | null;
}

export default function ReviewExtraction({ proposal, changes, confidence, provider, file }: Props) {
    const [fields, setFields] = useState<Record<string, boolean>>(
        Object.fromEntries(changes.fields.map(f => [f.key, true]))
    );
    const [records, setRecords] = useState<Record<string, boolean>>(
        Object.fromEntries(changes.records.map(r => [r.key, true]))
    );

    const form = useForm({});

    const toggleField = (k: string) => setFields(p => ({ ...p, [k]: !p[k] }));
    const toggleRecord = (k: string) => setRecords(p => ({ ...p, [k]: !p[k] }));

    const submit = () => {
        form.transform(() => ({
            fields: Object.keys(fields).filter(k => fields[k]),
            agency: !!records.agency,
            company: !!records.company,
            contact: !!records.contact,
            follow_up: !!records.follow_up,
        }));
        form.post(`/proposals/${proposal.id}/review`);
    };

    const selectedCount = Object.values(fields).filter(Boolean).length + Object.values(records).filter(Boolean).length;

    return (
        <AppLayout>
            <Head title="Review Extraction" />
            <div className="mx-auto max-w-2xl p-6">
                <PageHeader
                    icon={Sparkles}
                    title="Review Extracted Details"
                    description={`From ${file ?? 'your document'} — choose what to apply to ${proposal.proposal_number}.`}
                    actions={<Button href={`/proposals/${proposal.id}`} variant="secondary">Skip</Button>}
                />

                {confidence != null && (
                    <div className="mb-5 flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/[0.04] px-4 py-3">
                        <Sparkles className="h-4 w-4 shrink-0 text-primary" />
                        <p className="text-sm text-foreground">
                            Extracted with <span className="font-semibold">{Math.round(confidence * 100)}% confidence</span> via {provider}.
                            Toggle anything you don't want applied.
                        </p>
                    </div>
                )}

                {changes.fields.length > 0 && (
                    <Card className="mb-5">
                        <CardHeader><CardTitle>Proposal Fields</CardTitle></CardHeader>
                        <CardContent className="space-y-1">
                            {changes.fields.map(f => (
                                <button
                                    type="button"
                                    key={f.key}
                                    onClick={() => toggleField(f.key)}
                                    className="flex w-full items-start gap-3 rounded-lg px-2 py-2.5 text-left transition-colors hover:bg-secondary/50"
                                >
                                    <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors ${fields[f.key] ? 'border-primary bg-primary text-white' : 'border-border'}`}>
                                        {fields[f.key] && <Check className="h-3.5 w-3.5" />}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                            {f.label}
                                            {!f.changed && <span className="rounded bg-secondary px-1.5 py-0.5 text-[10px] normal-case tracking-normal">no change</span>}
                                        </span>
                                        {f.current && f.changed && (
                                            <span className="block truncate text-xs text-muted-foreground line-through">{f.current}</span>
                                        )}
                                        <span className="block text-sm font-medium text-foreground">{f.extracted}</span>
                                    </span>
                                </button>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {changes.records.length > 0 && (
                    <Card className="mb-5">
                        <CardHeader><CardTitle>Linked Records</CardTitle></CardHeader>
                        <CardContent className="space-y-1">
                            {changes.records.map(r => (
                                <button
                                    type="button"
                                    key={r.key}
                                    onClick={() => toggleRecord(r.key)}
                                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left transition-colors hover:bg-secondary/50"
                                >
                                    <span className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors ${records[r.key] ? 'border-primary bg-primary text-white' : 'border-border'}`}>
                                        {records[r.key] && <Check className="h-3.5 w-3.5" />}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-xs font-medium uppercase tracking-wide text-muted-foreground">{r.label}</span>
                                        <span className="block truncate text-sm font-medium text-foreground">{r.value}</span>
                                    </span>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${r.action === 'create' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-secondary text-muted-foreground'}`}>
                                        {r.action === 'create' ? 'Create' : 'Link existing'}
                                    </span>
                                </button>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {changes.fields.length === 0 && changes.records.length === 0 && (
                    <Card className="mb-5">
                        <CardContent className="py-8 text-center">
                            <FileText className="mx-auto mb-2 h-7 w-7 text-muted-foreground/50" />
                            <p className="text-sm text-muted-foreground">Nothing usable was extracted from this document.</p>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center justify-end gap-3">
                    <Link href={`/proposals/${proposal.id}`} className="text-sm font-medium text-muted-foreground hover:text-foreground">Skip for now</Link>
                    <Button onClick={submit} disabled={form.processing || selectedCount === 0} iconRight={ArrowRight}>
                        {form.processing ? 'Applying…' : `Apply ${selectedCount} selected`}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
