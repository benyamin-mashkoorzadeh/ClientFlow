"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { api } from "@/lib/api";
import { formatInvoiceDate, formatInvoiceRate } from "@/lib/invoice-display";
import { formatCents } from "@/lib/money";
import { ApiError, type Invoice, type InvoiceItem, type InvoiceStatus } from "@/lib/types";
import { InvoiceStatusBadge } from "@/components/invoice-status-badge";
import { PageHeader } from "@/components/page-header";

function errorMessage(cause: unknown, fallback: string): string {
  if (!(cause instanceof ApiError)) return fallback;
  if (cause.status === 401) return "Your session has expired. Sign in again to continue.";
  if (cause.status === 403) return "You do not have access to this invoice or action.";
  return Object.values(cause.errors).flat()[0] ?? cause.message;
}

export function InvoiceDetail() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [missing, setMissing] = useState(false);
  const [retry, setRetry] = useState(0);
  const [busy, setBusy] = useState<"status" | "delete" | "pdf" | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const busyRef = useRef(false);

  useEffect(() => {
    let active = true;
    void api.invoice(id).then((response) => {
      if (active) { setInvoice(response.data); setLoadError(null); setMissing(false); }
    }).catch((cause: unknown) => {
      if (!active) return;
      if (cause instanceof ApiError && cause.status === 404) setMissing(true);
      else setLoadError(errorMessage(cause, "The invoice could not be loaded."));
    });
    return () => { active = false; };
  }, [id, retry]);

  const transition = async (status: InvoiceStatus) => {
    if (!invoice || busyRef.current) return;
    if (status === "cancelled" && !window.confirm(`Cancel invoice ${invoice.invoice_number}? This cannot be undone.`)) return;
    busyRef.current = true; setBusy("status"); setActionError(null);
    try {
      const response = await api.updateInvoiceStatus(id, status);
      setInvoice(response.data);
    } catch (cause) {
      setActionError(errorMessage(cause, "The invoice status could not be changed."));
    } finally {
      busyRef.current = false; setBusy(null);
    }
  };

  const remove = async () => {
    if (!invoice || busyRef.current || !window.confirm(`Delete draft invoice ${invoice.invoice_number}? This cannot be undone.`)) return;
    busyRef.current = true; setBusy("delete"); setActionError(null);
    try {
      await api.deleteInvoice(id);
      router.push("/invoices");
      router.refresh();
    } catch (cause) {
      setActionError(errorMessage(cause, "The invoice could not be deleted."));
      busyRef.current = false; setBusy(null);
    }
  };

  const downloadPdf = async () => {
    if (!invoice || busyRef.current) return;
    busyRef.current = true; setBusy("pdf"); setActionError(null);
    try {
      const { blob, filename } = await api.downloadInvoicePdf(id);
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = objectUrl;
      link.download = (filename ?? `invoice-${invoice.invoice_number}.pdf`).replace(/[\\/]/g, "-");
      document.body.append(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
    } catch (cause) {
      setActionError(errorMessage(cause, "The PDF could not be downloaded."));
    } finally {
      busyRef.current = false; setBusy(null);
    }
  };

  if (missing) return <div className="notice notice-warning" role="alert"><strong>Invoice not found</strong><span>This invoice may have been deleted or you may not have access to it.</span><Link href="/invoices" className="text-button">Back to invoices</Link></div>;
  if (loadError) return <div className="notice notice-warning" role="alert"><strong>Couldn’t load invoice</strong><span>{loadError}</span>{loadError.includes("session has expired") ? <Link href={`/login?next=${encodeURIComponent(`/invoices/${id}`)}`} className="text-button">Sign in</Link> : <button type="button" className="text-button" onClick={() => { setLoadError(null); setRetry((value) => value + 1); }}>Try again</button>}</div>;
  if (!invoice) return <div className="screen-state" role="status"><span className="spinner" />Loading invoice...</div>;

  const transitions: { status: InvoiceStatus; label: string }[] = invoice.status === "draft"
    ? [{ status: "sent", label: "Mark as sent" }, { status: "cancelled", label: "Cancel invoice" }]
    : invoice.status === "sent"
      ? [{ status: "paid", label: "Mark as paid" }, { status: "cancelled", label: "Cancel invoice" }]
      : [];

  return <div className="invoice-detail">
    <Link href="/invoices" className="back-link">← Back to invoices</Link>
    <PageHeader eyebrow="Invoice detail" title={invoice.invoice_number} description={invoice.client.company ? `${invoice.client.name} — ${invoice.client.company}` : invoice.client.name} />
    <section className="panel invoice-summary" aria-label="Invoice summary">
      <div className="invoice-summary-top">
        <div className="badge-stack"><InvoiceStatusBadge status={invoice.status} />{invoice.is_overdue && <span className="overdue-badge">Overdue</span>}</div>
        <strong className="invoice-summary-total">{formatCents(invoice.total_cents, invoice.currency_code)}</strong>
      </div>
      <div className="invoice-meta-grid">
        <div><span>Client</span><Link href={`/clients/${invoice.client_id}`}>{invoice.client.name}</Link></div>
        <div><span>Issue date</span><strong>{formatInvoiceDate(invoice.issue_date)}</strong></div>
        <div><span>Due date</span><strong>{formatInvoiceDate(invoice.due_date)}</strong></div>
        <div><span>Currency</span><strong>{invoice.currency_code}</strong></div>
      </div>
      <div className="invoice-actions">
        {invoice.status === "draft" && <Link href={`/invoices/${invoice.id}/edit`} className="secondary-button">Edit draft</Link>}
        <button type="button" className="secondary-button" onClick={() => void downloadPdf()} disabled={busy !== null}>{busy === "pdf" ? "Preparing PDF..." : "Download PDF"}</button>
        {transitions.map(({ status, label }) => <button key={status} type="button" className={status === "cancelled" ? "danger-button" : "primary-button invoice-action-primary"} onClick={() => void transition(status)} disabled={busy !== null}>{busy === "status" ? "Updating..." : label}</button>)}
        {invoice.status === "draft" && <button type="button" className="danger-button" onClick={() => void remove()} disabled={busy !== null}>{busy === "delete" ? "Deleting..." : "Delete draft"}</button>}
      </div>
      {actionError && <div className="form-error" role="alert">{actionError}</div>}
    </section>
    <section className="panel invoice-items-panel" aria-labelledby="invoice-items-heading">
      <div className="panel-heading"><div><p className="eyebrow">Billed work</p><h2 id="invoice-items-heading">Line items</h2></div><span className="panel-kicker">{invoice.items.length} {invoice.items.length === 1 ? "item" : "items"}</span></div>
      <InvoiceItems items={invoice.items} currency={invoice.currency_code} />
      <dl className="invoice-totals">
        <div><dt>Subtotal</dt><dd>{formatCents(invoice.subtotal_cents, invoice.currency_code)}</dd></div>
        <div><dt>Discount</dt><dd>{formatCents(invoice.discount_cents, invoice.currency_code)}</dd></div>
        <div><dt>Tax</dt><dd>{formatCents(invoice.tax_cents, invoice.currency_code)}</dd></div>
        <div className="invoice-total-row"><dt>Total</dt><dd>{formatCents(invoice.total_cents, invoice.currency_code)}</dd></div>
      </dl>
    </section>
    {invoice.notes && <section className="panel invoice-notes" aria-labelledby="invoice-notes-heading"><h2 id="invoice-notes-heading">Notes</h2><p>{invoice.notes}</p></section>}
  </div>;
}

function InvoiceItems({ items, currency }: { items: InvoiceItem[]; currency: string }) {
  return <div className="invoice-items-wrap"><table className="invoice-items-table">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Tax</th><th>Subtotal</th><th>Discount amount</th><th>Tax amount</th><th>Line total</th></tr></thead>
    <tbody>{items.map((item) => <tr key={item.id}>
      <td data-label="Description">{item.description}</td>
      <td data-label="Quantity">{item.quantity}</td>
      <td data-label="Unit price">{formatCents(item.unit_price_cents, currency)}</td>
      <td data-label="Discount rate">{formatInvoiceRate(item.discount_rate)}</td>
      <td data-label="Tax rate">{formatInvoiceRate(item.tax_rate)}</td>
      <td data-label="Subtotal">{formatCents(item.line_subtotal_cents, currency)}</td>
      <td data-label="Discount amount">{formatCents(item.line_discount_cents, currency)}</td>
      <td data-label="Tax amount">{formatCents(item.line_tax_cents, currency)}</td>
      <td data-label="Line total">{formatCents(item.line_total_cents, currency)}</td>
    </tr>)}</tbody>
  </table></div>;
}
