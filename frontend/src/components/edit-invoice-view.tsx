"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Invoice } from "@/lib/types";
import { InvoiceForm } from "@/components/invoice-form";
import { PageHeader } from "@/components/page-header";

export function EditInvoiceView() {
  const { id } = useParams<{ id: string }>();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [error, setError] = useState<{ status?: number; message: string } | null>(null);
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    let active = true;
    void api.invoice(id).then((response) => { if (active) setInvoice(response.data); }).catch((cause: unknown) => {
      if (!active) return;
      if (cause instanceof ApiError) setError({ status: cause.status, message: cause.status === 401 ? "Your session has expired. Sign in again to continue." : cause.status === 403 ? "You do not have access to this invoice." : cause.status === 404 ? "This invoice may have been deleted or you may not have access to it." : cause.status >= 500 ? "The invoice could not be loaded. Please try again." : cause.message });
      else setError({ message: "The invoice could not be loaded." });
    });
    return () => { active = false; };
  }, [id, retry]);

  if (error) return <div className="notice notice-warning" role="alert"><strong>{error.status === 404 ? "Invoice not found" : "Couldn’t load invoice"}</strong><span>{error.message}</span>{error.status === 401 ? <Link href={`/login?next=${encodeURIComponent(`/invoices/${id}/edit`)}`} className="text-button">Sign in</Link> : <><Link href={`/invoices/${id}`} className="text-button">Back to invoice</Link>{error.status !== 404 && error.status !== 403 && <button type="button" className="text-button invoice-retry" onClick={() => { setError(null); setRetry((value) => value + 1); }}>Try again</button>}</>}</div>;
  if (!invoice) return <div className="screen-state" role="status"><span className="spinner" />Loading invoice...</div>;
  if (invoice.status !== "draft") return <div><Link href={`/invoices/${invoice.id}`} className="back-link">← Back to invoice</Link><PageHeader eyebrow="Invoice / Edit" title={invoice.invoice_number} description="This invoice can no longer be edited." /><div className="notice notice-warning" role="status"><strong>Only drafts can be edited</strong><span>This invoice is {invoice.status}. Its details and line items are now read-only.</span><Link href={`/invoices/${invoice.id}`} className="text-button">View invoice</Link></div></div>;

  return <><Link href={`/invoices/${invoice.id}`} className="back-link">← Back to invoice</Link><PageHeader eyebrow="Invoice / Edit" title={`Edit ${invoice.invoice_number}`} description="Update this draft before sending it." /><section className="form-panel invoice-form-panel"><InvoiceForm key={invoice.id} invoice={invoice} /></section></>;
}
