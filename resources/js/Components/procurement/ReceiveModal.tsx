import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';

interface ReceivableItem {
    id: number;
    description: string;
    sku: string | null;
    outstanding: number;
    quantity_ordered: number;
    quantity_received: number;
}

interface Props {
    open: boolean;
    onClose: () => void;
    orderId: number;
    items: ReceivableItem[];
}

export function ReceiveModal({ open, onClose, orderId, items }: Props) {
    const receivable = items.filter(i => i.outstanding > 0);
    const [qty, setQty] = useState<Record<number, string>>(
        Object.fromEntries(receivable.map(i => [i.id, String(i.outstanding)])),
    );
    const [processing, setProcessing] = useState(false);

    const post = (payload: { receive_all: boolean } | { lines: { id: number; quantity: number }[] }) => {
        setProcessing(true);
        router.post(`/procurement/purchase-orders/${orderId}/receive`, payload, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    };

    const receiveSelected = () => {
        const lines = receivable
            .map(i => ({ id: i.id, quantity: parseFloat(qty[i.id] ?? '0') }))
            .filter(l => l.quantity > 0);
        if (lines.length === 0) return;
        post({ lines });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title="Receive Goods"
            description="Enter the quantity received for each line, then post to stock."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button variant="secondary" onClick={() => post({ receive_all: true })} disabled={processing || receivable.length === 0}>
                        Receive everything
                    </Button>
                    <Button variant="success" onClick={receiveSelected} disabled={processing || receivable.length === 0}>
                        {processing ? 'Posting…' : 'Receive'}
                    </Button>
                </>
            }
        >
            {receivable.length === 0 ? (
                <p className="py-4 text-sm text-muted-foreground">Every line on this purchase order is already fully received.</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase text-muted-foreground/70">
                            <th className="pb-2">Item</th>
                            <th className="pb-2 text-right">Ordered</th>
                            <th className="pb-2 text-right">Received</th>
                            <th className="pb-2 text-right">Receive now</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {receivable.map(i => (
                            <tr key={i.id}>
                                <td className="py-2">
                                    <span className="block font-medium text-foreground">{i.description}</span>
                                    {i.sku && <span className="block font-mono text-xs text-muted-foreground">{i.sku}</span>}
                                </td>
                                <td className="py-2 text-right text-muted-foreground">{i.quantity_ordered}</td>
                                <td className="py-2 text-right text-muted-foreground">{i.quantity_received}</td>
                                <td className="py-2 text-right">
                                    <input
                                        type="number" step="0.001" min="0" max={i.outstanding}
                                        className="input h-9 w-24 text-right"
                                        value={qty[i.id] ?? ''}
                                        onChange={e => setQty(q => ({ ...q, [i.id]: e.target.value }))}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}
