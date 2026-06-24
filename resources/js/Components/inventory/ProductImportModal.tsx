import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';

export function ProductImportModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = () => {
        if (!form.data.file) return;
        form.post('/inventory/products/import', {
            forceFormData: true,
            onSuccess: () => { form.reset(); onClose(); },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Import products"
            description="Upload an Excel (.xlsx / .xls) or CSV file of your products."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit} disabled={form.processing || !form.data.file}>
                        {form.processing ? 'Importing…' : 'Import'}
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                <p className="text-sm text-muted-foreground">
                    We match standard columns (SKU, name, price, cost, currency, barcode, category, manufacturer, quantity…) and
                    <span className="font-medium text-foreground"> keep any extra columns</span> on each product. Existing products are matched by SKU and updated; a quantity column sets the on-hand.
                </p>
                <input
                    type="file"
                    accept=".xlsx,.xls,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    onChange={e => form.setData('file', e.target.files?.[0] ?? null)}
                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium file:text-foreground hover:file:bg-secondary/70"
                />
                {form.errors.file && <p className="text-xs text-destructive">{form.errors.file}</p>}
                {form.progress && (
                    <div className="h-1.5 overflow-hidden rounded-full bg-secondary">
                        <div className="h-full rounded-full bg-brand-gradient transition-all" style={{ width: `${form.progress.percentage}%` }} />
                    </div>
                )}
            </div>
        </Modal>
    );
}
