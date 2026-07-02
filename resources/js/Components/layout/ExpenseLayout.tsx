// Expenses now lives inside the CRM section — its pages render with the CRM
// sidebar (which carries the Expenses nav group: Overview / Expenses /
// Categories / Recurring / Reports / QuickBooks). Kept as a thin alias so the
// Expenses pages don't each need to swap their layout import. See CrmLayout.
export { CrmLayout as ExpenseLayout } from '@/Components/layout/CrmLayout';
