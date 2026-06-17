import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { formatCurrency } from '@/Lib/utils';
import { Calculator, Plus, Trash2, Pencil, Check, X } from 'lucide-react';

export interface CostLine {
    id: number;
    description: string;
    category: string;
    category_label: string;
    amount: number;
}

export interface MarginSummary {
    currency: string;
    bid: number | null;
    cost: number;
    profit: number | null;
    margin: number | null;
    has_bid: boolean;
    line_count: number;
}

interface Props {
    proposalId: number;
    costs: CostLine[];
    margin: MarginSummary;
    categories: Array<{ value: string; label: string }>;
    canEdit: boolean;
}

const profitClass = (v: number | null) =>
    v == null ? 'text-foreground' : v > 0 ? 'text-emerald-600 dark:text-emerald-400' : v < 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground';

function Stat({ label, value, accent }: { label: string; value: string; accent?: string }) {
    return (
        <div className="rounded-xl border border-border bg-card px-3 py-2.5">
            <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className={`mt-0.5 text-lg font-bold tabular-nums ${accent ?? 'text-foreground'}`}>{value}</p>
        </div>
    );
}

interface CostForm { description: string; category: string; amount: string }

// Defined at module scope (not inside CostMarginPanel) so it keeps a stable
// component identity across re-renders — otherwise React remounts the inputs on
// every keystroke and focus jumps back to the autoFocus'd description field.
function EditRow({ form, setForm, categories, valid, onSave, onCancel }: {
    form: CostForm;
    setForm: React.Dispatch<React.SetStateAction<CostForm>>;
    categories: Array<{ value: string; label: string }>;
    valid: boolean;
    onSave: () => void;
    onCancel: () => void;
}) {
    return (
        <div className="grid grid-cols-1 gap-2 rounded-xl border border-primary/40 bg-primary/5 p-3 sm:grid-cols-[1fr_10rem_8rem_auto]">
            <input
                className="input"
                placeholder="Description (e.g. UTM DPP cost from China)"
                value={form.description}
                autoFocus
                onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
            />
            <Select value={form.category} onChange={v => setForm(f => ({ ...f, category: v }))} options={categories} />
            <NumberInput
                className="input" placeholder="0.00"
                value={form.amount}
                onChange={e => setForm(f => ({ ...f, amount: e.target.value }))}
            />
            <div className="flex items-center gap-1">
                <Button size="sm" icon={Check} onClick={onSave} disabled={!valid}>Save</Button>
                <button type="button" onClick={onCancel} className="rounded-lg p-1.5 text-muted-foreground hover:text-foreground" title="Cancel"><X className="h-4 w-4" /></button>
            </div>
        </div>
    );
}

export function CostMarginPanel({ proposalId, costs, margin, categories, canEdit }: Props) {
    const { currency } = margin;
    const [adding, setAdding] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const blank: CostForm = { description: '', category: 'equipment', amount: '' };
    const [form, setForm] = useState<CostForm>(blank);

    const startAdd = () => { setEditingId(null); setForm(blank); setAdding(true); };
    const startEdit = (c: CostLine) => {
        setAdding(false);
        setEditingId(c.id);
        setForm({ description: c.description, category: c.category, amount: String(c.amount) });
    };
    const cancel = () => { setAdding(false); setEditingId(null); setForm(blank); };

    const valid = form.description.trim() !== '' && form.amount !== '' && Number(form.amount) >= 0;

    const submitAdd = () => {
        if (!valid) return;
        router.post(`/proposals/${proposalId}/costs`, { ...form, amount: form.amount }, {
            preserveScroll: true,
            onSuccess: cancel,
        });
    };
    const submitEdit = (id: number) => {
        if (!valid) return;
        router.patch(`/proposals/${proposalId}/costs/${id}`, { ...form, amount: form.amount }, {
            preserveScroll: true,
            onSuccess: cancel,
        });
    };
    const remove = (c: CostLine) => {
        if (confirm(`Remove "${c.description}" from the cost estimate?`)) {
            router.delete(`/proposals/${proposalId}/costs/${c.id}`, { preserveScroll: true });
        }
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2"><Calculator className="h-4 w-4 text-primary" /> Cost &amp; Margin</CardTitle>
                {canEdit && !adding && (
                    <Button size="sm" variant="secondary" icon={Plus} onClick={startAdd}>Add cost</Button>
                )}
            </CardHeader>
            <CardContent>
                {/* Summary */}
                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Stat label="Bid value" value={margin.bid != null ? formatCurrency(margin.bid, currency) : '—'} />
                    <Stat label="Est. cost" value={formatCurrency(margin.cost, currency)} />
                    <Stat label="Potential profit" value={margin.profit != null ? formatCurrency(margin.profit, currency) : '—'} accent={profitClass(margin.profit)} />
                    <Stat label="Margin" value={margin.margin != null ? `${margin.margin}%` : '—'} accent={profitClass(margin.margin)} />
                </div>

                {!margin.has_bid && (
                    <p className="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                        Set a proposal (bid) value to see potential profit and margin.
                    </p>
                )}

                {/* Cost lines */}
                <div className="space-y-1.5">
                    {costs.length === 0 && !adding && (
                        <p className="text-sm text-muted-foreground">
                            No costs tracked yet. {canEdit ? 'Add the cost of equipment, shipping, installation, etc. to estimate profit.' : ''}
                        </p>
                    )}
                    {costs.map(c => (
                        editingId === c.id ? (
                            <EditRow key={c.id} form={form} setForm={setForm} categories={categories} valid={valid} onSave={() => submitEdit(c.id)} onCancel={cancel} />
                        ) : (
                            <div key={c.id} className="flex items-center gap-2.5 rounded-xl border border-border px-3 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-foreground">{c.description}</p>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{c.category_label}</p>
                                </div>
                                <span className="shrink-0 text-sm font-semibold tabular-nums text-foreground">{formatCurrency(c.amount, currency)}</span>
                                {canEdit && (
                                    <div className="flex shrink-0 items-center gap-1">
                                        <button type="button" onClick={() => startEdit(c)} className="text-muted-foreground hover:text-primary" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                                        <button type="button" onClick={() => remove(c)} className="text-muted-foreground hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                                    </div>
                                )}
                            </div>
                        )
                    ))}
                    {adding && <EditRow form={form} setForm={setForm} categories={categories} valid={valid} onSave={submitAdd} onCancel={cancel} />}
                </div>

                {/* Total + caveat */}
                {costs.length > 0 && (
                    <div className="mt-3 flex items-center justify-between border-t border-border pt-3 text-sm">
                        <span className="font-medium text-muted-foreground">Total estimated cost</span>
                        <span className="font-bold tabular-nums text-foreground">{formatCurrency(margin.cost, currency)}</span>
                    </div>
                )}
                <p className="mt-3 text-[11px] leading-relaxed text-muted-foreground">
                    Estimate only — add what you know now (equipment, shipping, installation). Costs not yet known until project
                    completion (extra travel, overhead) aren't captured until you enter them.
                </p>
            </CardContent>
        </Card>
    );
}
