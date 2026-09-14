import { InvoiceForm } from "@/components/invoice-form";
import { PageHeader } from "@/components/page-header";

export default function NewInvoicePage() {
  return <><PageHeader eyebrow="Invoices / New" title="Create an invoice" description="Build a draft for the work you have delivered." /><section className="form-panel invoice-form-panel"><InvoiceForm /></section></>;
}
