// Finance (AR) now lives inside the CRM section — its pages render with the CRM
// sidebar, which carries the Finance nav group (Overview / Receivables / Credit
// Notes). Kept as a thin alias so the Finance pages don't each need to swap
// their layout import. See CrmLayout for the actual shell.
export { CrmLayout as FinanceLayout } from '@/Components/layout/CrmLayout';
