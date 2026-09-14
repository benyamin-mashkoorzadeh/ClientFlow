"use client";

import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { formatInvoiceDate } from "@/lib/invoice-display";
import { formatCents } from "@/lib/money";
import { ApiError, type Client, type ClientListResponse, type Invoice, type InvoiceListResponse, type InvoiceStatus } from "@/lib/types";
import { InvoiceStatusBadge } from "@/components/invoice-status-badge";
import { PageHeader } from "@/components/page-header";

const statuses: InvoiceStatus[] = ["draft", "sent", "paid", "cancelled"];

export function InvoicesView() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const page = Math.max(Number(params.get("page") ?? "1") || 1, 1);
  const querySearch = params.get("search") ?? "";
  const rawStatus = params.get("status") ?? "";
  const status = statuses.find((value) => value === rawStatus);
  const clientId = params.get("client_id") ?? "";
  const [searchState, setSearchState] = useState({ querySearch, value: querySearch });
  const search = searchState.querySearch === querySearch ? searchState.value : querySearch;
  const setSearch = (value: string) => setSearchState({ querySearch, value });
  const [retry, setRetry] = useState(0);
  const [clientRetry, setClientRetry] = useState(0);
  const requestKey = JSON.stringify([page, querySearch, status, clientId, retry]);
  const [invoiceRequest, setInvoiceRequest] = useState<{ key: string; result: InvoiceListResponse | null; error: string | null } | null>(null);
  const loading = invoiceRequest?.key !== requestKey;
  const result = loading ? null : invoiceRequest.result;
  const error = loading ? null : invoiceRequest.error;
  const [clientRequest, setClientRequest] = useState<{ key: number; clients: Client[]; error: string | null } | null>(null);
  const clientLoading = clientRequest?.key !== clientRetry;
  const clients = clientLoading ? [] : clientRequest.clients;
  const clientError = clientLoading ? null : clientRequest.error;

  const navigate = (nextPage: number, nextSearch = querySearch, nextStatus = status ?? "", nextClientId = clientId, replace = false) => {
    const next = new URLSearchParams();
    if (nextPage > 1) next.set("page", String(nextPage));
    if (nextSearch.trim()) next.set("search", nextSearch.trim());
    if (nextStatus) next.set("status", nextStatus);
    if (nextClientId) next.set("client_id", nextClientId);
    const href = `${pathname}${next.toString() ? `?${next}` : ""}`;
    if (replace) router.replace(href); else router.push(href);
  };

  useEffect(() => {
    if (search.trim() === querySearch) return;
    const timer = window.setTimeout(() => navigate(1, search, status ?? "", clientId, true), 350);
    return () => window.clearTimeout(timer);
    // URL navigation is deliberately driven by the current search input.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, querySearch, status, clientId]);

  useEffect(() => {
    let active = true;
    void api.invoices({ page, search: querySearch, status, client_id: clientId ? Number(clientId) : undefined })
      .then((response) => { if (active) setInvoiceRequest({ key: requestKey, result: response, error: null }); })
      .catch((cause: unknown) => { if (active) setInvoiceRequest({ key: requestKey, result: null, error: cause instanceof ApiError ? cause.message : "Invoices could not be loaded." }); });
    return () => { active = false; };
  }, [page, querySearch, status, clientId, retry, requestKey]);

  useEffect(() => {
    let active = true;
    const load = async () => {
      try {
        const first = await api.clients({ page: 1, per_page: 50 });
        const all = [...first.data];
        for (let nextPage = 2; nextPage <= first.meta.last_page; nextPage += 1) {
          const next: ClientListResponse = await api.clients({ page: nextPage, per_page: 50 });
          all.push(...next.data);
        }
        if (active) setClientRequest({ key: clientRetry, clients: all, error: null });
      } catch (cause) {
        if (active) setClientRequest({ key: clientRetry, clients: [], error: cause instanceof ApiError ? cause.message : "Clients could not be loaded." });
      }
    };
    void load();
    return () => { active = false; };
  }, [clientRetry]);

  const hasFilters = Boolean(querySearch || status || clientId);
  return <div>
    <PageHeader eyebrow="Cash flow" title="Invoices" description="Track what you have billed and what has been paid." />
    <div className="module-toolbar project-toolbar invoice-toolbar">
      <form className="search-form" role="search" onSubmit={(event) => { event.preventDefault(); navigate(1, search); }}>
        <span aria-hidden="true">⌕</span>
        <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search invoice or client..." aria-label="Search invoices" />
        <button type="submit">Search</button>
      </form>
      <div className="project-filters invoice-filters">
        <select value={status ?? ""} onChange={(event) => navigate(1, search, event.target.value, clientId)} aria-label="Filter invoices by status">
          <option value="">All statuses</option><option value="draft">Draft</option><option value="sent">Sent</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option>
        </select>
        <select value={clientId} onChange={(event) => navigate(1, search, status ?? "", event.target.value)} aria-label="Filter invoices by client" disabled={clientLoading || Boolean(clientError)}>
          <option value="">{clientLoading ? "Loading clients..." : clientError ? "Clients unavailable" : "All clients"}</option>
          {clientId && !clients.some((client) => String(client.id) === clientId) && <option value={clientId}>Selected client unavailable</option>}
          {clients.map((client) => <option key={client.id} value={client.id}>{client.name}{client.company ? ` — ${client.company}` : ""}</option>)}
        </select>
      </div>
      <Link href="/invoices/new" className="primary-button compact-button">New invoice <span aria-hidden="true">+</span></Link>
    </div>
    {clientError && <div className="invoice-filter-error" role="alert">Client filter unavailable: {clientError} <button type="button" className="text-button" onClick={() => setClientRetry((value) => value + 1)}>Retry</button></div>}
    {hasFilters && <button type="button" className="clear-filters" onClick={() => { setSearch(""); navigate(1, "", "", ""); }}>Clear filters</button>}
    {error ? <div className="notice notice-warning" role="alert"><strong>Couldn’t load invoices</strong><span>{error}</span><button type="button" className="text-button" onClick={() => setRetry((value) => value + 1)}>Try again</button></div>
      : loading ? <div className="screen-state" role="status"><span className="spinner" />Loading invoices...</div>
      : !result || result.data.length === 0 ? <div className="empty-panel"><div className="empty-symbol" aria-hidden="true">▤</div><h2>{hasFilters ? "No invoices found" : "No invoices yet"}</h2><p>{hasFilters ? "Try another search or clear the filters." : "Create your first draft invoice to get started."}</p>{!hasFilters && <Link href="/invoices/new" className="primary-button compact-button">Create an invoice <span aria-hidden="true">+</span></Link>}</div>
      : <><InvoiceTable invoices={result.data} /><InvoicePagination meta={result.meta} onChange={(nextPage) => navigate(nextPage)} /></>}
  </div>;
}

function InvoiceTable({ invoices }: { invoices: Invoice[] }) {
  return <div className="client-table-wrap"><table className="client-table invoice-table">
    <thead><tr><th>Invoice</th><th>Client</th><th>Issue date</th><th>Due date</th><th>Status</th><th>Total</th><th><span className="sr-only">Actions</span></th></tr></thead>
    <tbody>{invoices.map((invoice) => <tr key={invoice.id}>
      <td data-label="Invoice"><Link href={`/invoices/${invoice.id}`} className="client-name">{invoice.invoice_number}</Link></td>
      <td data-label="Client"><Link href={`/clients/${invoice.client_id}`} className="related-link">{invoice.client.name}</Link></td>
      <td data-label="Issued">{formatInvoiceDate(invoice.issue_date)}</td>
      <td data-label="Due" className={invoice.is_overdue ? "overdue-text" : ""}>{formatInvoiceDate(invoice.due_date)}{invoice.is_overdue && <span className="overdue-badge invoice-overdue">Overdue</span>}</td>
      <td data-label="Status"><InvoiceStatusBadge status={invoice.status} /></td>
      <td data-label="Total" className="invoice-money">{formatCents(invoice.total_cents, invoice.currency_code)}</td>
      <td className="actions-cell"><Link href={`/invoices/${invoice.id}`} className="table-action">View</Link></td>
    </tr>)}</tbody>
  </table></div>;
}

function InvoicePagination({ meta, onChange }: { meta: InvoiceListResponse["meta"]; onChange: (page: number) => void }) {
  if (meta.last_page <= 1) return null;
  return <div className="pagination"><span>Showing page {meta.current_page} of {meta.last_page} · {meta.total} invoices</span><div><button type="button" disabled={meta.current_page <= 1} onClick={() => onChange(meta.current_page - 1)}>← Previous</button><button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => onChange(meta.current_page + 1)}>Next →</button></div></div>;
}
