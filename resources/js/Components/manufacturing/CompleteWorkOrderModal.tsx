import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';

interface RequirementRow { sku: string; name: string; required: number; available: number; sufficient: boolean; unit_of_measure: string }

interface Props {
    open: boolean;
    onClose: () => void;
    orderId: number;
    number: string;
    defaultQuantity: number;
    requirements: RequirementRow[];
}

export function CompleteWorkOrderModal({ open, onClose, orderId, number, defaultQuantity, requirements }: Props) {
    const form = useForm({ quantity: String(defaultQuantity > 0 ? defaultQuantity : 1) });
    const shortages = requirements.filter(r => !r.sufficient);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/manufacturing/work-orders/${orderId}/complete`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={`Build ${number}`}
            description="Consume components and produce finished goods into stock."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button variant="success" onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Building…' : 'Build & post to stock'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Quantity to build *</label>
                    <input type="number" step="0.001" min="0" className="input w-40" value={form.data.quantity} onChange={e => form.setData('quantity', e.target.value)} autoFocus />
                    {form.errors.quantity && <p className="mt-1 text-xs text-destructive">{form.errors.quantity}</p>}
                </div>

                <div>
                    <p className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Component requirements (for planned qty)</p>
                    {requirements.length === 0 ? (
                        <p className="text-sm text-amber-600">This work order has no BOM, so there's nothing to consume.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-xs uppercase text-muted-foreground/70"><th className="pb-1">Component</th><th className="pb-1 text-right">Required</th><th className="pb-1 text-right">On hand</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {requirements.map((r, i) => (
                                    <tr key={i}>
                                        <td className="py-1.5"><span className="font-medium text-foreground">{r.name}</span> <span className="font-mono text-xs text-muted-foreground">{r.sku}</span></td>
                                        <td className="py-1.5 text-right text-foreground">{r.required} {r.unit_of_measure}</td>
                                        <td className={'py-1.5 text-right ' + (r.sufficient ? 'text-emerald-600' : 'font-semibold text-red-600')}>{r.available}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {shortages.length > 0 && (
                        <p className="mt-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            {shortages.length} component{shortages.length > 1 ? 's are' : ' is'} short for the full planned quantity — the build will fail unless you reduce the quantity or receive more stock first.
                        </p>
                    )}
                </div>
            </form>
        </Modal>
    );
}
