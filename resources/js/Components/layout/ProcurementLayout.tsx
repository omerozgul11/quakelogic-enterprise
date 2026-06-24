// Procurement now lives inside the CRM section — its pages render with the CRM
// sidebar (which carries the Procurement nav group). Kept as a thin alias so the
// pages don't each need to swap their layout import. See CrmLayout.
export { CrmLayout as ProcurementLayout } from '@/Components/layout/CrmLayout';
