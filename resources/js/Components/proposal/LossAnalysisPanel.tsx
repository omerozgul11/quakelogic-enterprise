import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { NumberInput } from '@/Components/ui/NumberInput';
import { Sparkles, AlertOctagon } from 'lucide-react';

export interface LossData {
    loss_reason: string | null;
    loss_competitor: string | null;
    loss_competitor_price: number | string | null;
    debrief_requested: boolean;
    protest_recommended: boolean;
    lessons_learned: string | null;
    loss_assessment: string | null;
}

interface Props {
    proposalId: number;
    data: LossData;
    canEdit: boolean;
}

export function LossAnalysisPanel({ proposalId, data, canEdit }: Props) {
    const [generating, setGenerating] = useState(false);
    const form = useForm({
        loss_reason: data.loss_reason ?? '',
        loss_competitor: data.loss_competitor ?? '',
        loss_competitor_price: data.loss_competitor_price != null ? String(data.loss_competitor_price) : '',
        debrief_requested: !!data.debrief_requested,
        protest_recommended: !!data.protest_recommended,
        lessons_learned: data.lessons_learned ?? '',
    });

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/proposals/${proposalId}/loss-analysis`, { preserveScroll: true });
    };
    const generate = () => {
        setGenerating(true);
        router.post(`/proposals/${proposalId}/loss-assessment`, {}, { preserveScroll: true, onFinish: () => setGenerating(false) });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2"><AlertOctagon className="h-4 w-4 text-rose-500" /> Loss Analysis</CardTitle>
                {canEdit && (
                    <Button size="sm" variant="secondary" icon={Sparkles} onClick={generate} disabled={generating}>
                        {generating ? 'Analyzing…' : 'AI assessment'}
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="space-y-4">
                    <div>
                        <label className="label">Why did we lose?</label>
                        <textarea className="input" rows={2} value={form.data.loss_reason} disabled={!canEdit} onChange={e => form.setData('loss_reason', e.target.value)} placeholder="Price, technical score, incumbent advantage…" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Winning competitor</label>
                            <input className="input" value={form.data.loss_competitor} disabled={!canEdit} onChange={e => form.setData('loss_competitor', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Their price (if known)</label>
                            <NumberInput className="input" value={form.data.loss_competitor_price} disabled={!canEdit} onChange={e => form.setData('loss_competitor_price', e.target.value)} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-5">
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" className="h-4 w-4 rounded border-border" checked={form.data.debrief_requested} disabled={!canEdit} onChange={e => form.setData('debrief_requested', e.target.checked)} />
                            Debrief requested
                        </label>
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" className="h-4 w-4 rounded border-border" checked={form.data.protest_recommended} disabled={!canEdit} onChange={e => form.setData('protest_recommended', e.target.checked)} />
                            Protest recommended
                        </label>
                    </div>
                    <div>
                        <label className="label">Lessons learned</label>
                        <textarea className="input" rows={2} value={form.data.lessons_learned} disabled={!canEdit} onChange={e => form.setData('lessons_learned', e.target.value)} />
                    </div>
                    {canEdit && (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>Save analysis</Button>
                        </div>
                    )}
                </form>

                {data.loss_assessment && (
                    <div className="mt-4 rounded-xl border border-border bg-secondary/40 p-4">
                        <p className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <Sparkles className="h-3.5 w-3.5 text-primary" /> AI Loss Assessment
                        </p>
                        <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">{data.loss_assessment}</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
