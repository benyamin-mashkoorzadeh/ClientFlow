"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { api } from "@/lib/api";
import { isSupportedCurrency } from "@/lib/currencies";
import { centsToInput, inputToCents } from "@/lib/money";
import { ApiError, type ApiValidationErrors, type Client, type ClientListResponse, type Invoice, type InvoiceItemPayload, type InvoicePayload } from "@/lib/types";
import { useAuth } from "@/context/auth-context";
import { InvoiceItemsEditor, type InvoiceItemDraft } from "@/components/invoice-items-editor";
import { CurrencySelect } from "@/components/currency-select";

const blankItem = (key: number): InvoiceItemDraft => ({ key, description: "", quantity: "1", unitPrice: "", discountRate: "0", taxRate: "0" });

function formItems(invoice?: Invoice): InvoiceItemDraft[] {
  if (!invoice?.items.length) return [blankItem(0)];
  return invoice.items.map((item, key) => ({
    key,
    description: item.description,
    quantity: String(item.quantity),
    unitPrice: centsToInput(item.unit_price_cents),
    discountRate: item.discount_rate,
    taxRate: item.tax_rate,
  }));
}

function validRate(value: string): boolean {
  return /^\d+(?:\.\d{1,2})?$/.test(value) && Number(value) <= 100;
}

export function InvoiceForm({ invoice }: { invoice?: Invoice }) {
  const router = useRouter();
  const { workspace } = useAuth();
  const [values, setValues] = useState(() => ({
    clientId: invoice ? String(invoice.client_id) : "",
    issueDate: invoice?.issue_date ?? "",
    dueDate: invoice?.due_date ?? "",
    currencyCode: invoice?.currency_code ?? "",
    notes: invoice?.notes ?? "",
  }));
  const [items, setItems] = useState<InvoiceItemDraft[]>(() => formItems(invoice));
  const nextKey = useRef(invoice?.items.length || 1);
  const busyRef = useRef(false);
  const [clients, setClients] = useState<Client[]>([]);
  const [clientLoading, setClientLoading] = useState(true);
  const [clientError, setClientError] = useState<{ message: string; status?: number } | null>(null);
  const [clientRetry, setClientRetry] = useState(0);
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [formError, setFormError] = useState<{ message: string; status?: number } | null>(null);
  const [saving, setSaving] = useState(false);
  const selectedCurrency = values.currencyCode || (invoice ? "" : workspace?.default_currency.toUpperCase() ?? "");

  useEffect(() => {
    let active = true;
    const load = async () => {
      try {
        const first = await api.clients({ page: 1, per_page: 50 });
        const all = [...first.data];
        for (let page = 2; page <= first.meta.last_page; page += 1) {
          const next: ClientListResponse = await api.clients({ page, per_page: 50 });
          all.push(...next.data);
        }
        if (active) { setClients(all); setClientError(null); }
      } catch (cause) {
        if (active) setClientError(cause instanceof ApiError ? { message: cause.status === 401 ? "Your session has expired. Sign in again to continue." : cause.status === 403 ? "You do not have access to clients in this workspace." : cause.status >= 500 ? "Clients could not be loaded. Please try again." : cause.message, status: cause.status } : { message: "Clients could not be loaded." });
      } finally {
        if (active) setClientLoading(false);
      }
    };
    void load();
    return () => { active = false; };
  }, [clientRetry]);

  const update = (field: keyof typeof values, value: string, errorKey: string) => {
    setValues((current) => ({ ...current, [field]: value }));
    setErrors((current) => { const next = { ...current }; delete next[errorKey]; return next; });
  };
  const updateItem = (index: number, field: keyof Omit<InvoiceItemDraft, "key">, value: string) => {
    setItems((current) => current.map((item, position) => position === index ? { ...item, [field]: value } : item));
    const backendField = field === "unitPrice" ? "unit_price_cents" : field === "discountRate" ? "discount_rate" : field === "taxRate" ? "tax_rate" : field;
    setErrors((current) => { const next = { ...current }; delete next[`items.${index}.${backendField}`]; return next; });
  };
  const addItem = () => {
    const key = nextKey.current++;
    setItems((current) => [...current, blankItem(key)]);
    setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => !key.startsWith("items"))));
  };
  const removeItem = (index: number) => {
    setItems((current) => current.length === 1 ? current : current.filter((_, position) => position !== index));
    setErrors((current) => Object.fromEntries(Object.entries(current).filter(([key]) => !key.startsWith("items"))));
  };

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busyRef.current) return;
    setErrors({}); setFormError(null);
    const localErrors: ApiValidationErrors = {};
    const itemPayloads: InvoiceItemPayload[] = items.map((item, index) => {
      const quantity = Number(item.quantity);
      const price = inputToCents(item.unitPrice);
      const discount = item.discountRate.trim() || "0";
      const tax = item.taxRate.trim() || "0";
      if (!item.description.trim()) localErrors[`items.${index}.description`] = ["Enter an item description."];
      if (!Number.isSafeInteger(quantity) || quantity < 1) localErrors[`items.${index}.quantity`] = ["Enter a whole quantity of at least 1."];
      if (price === null) localErrors[`items.${index}.unit_price_cents`] = ["Enter a non-negative amount with up to two decimal places."];
      if (!validRate(discount)) localErrors[`items.${index}.discount_rate`] = ["Enter a percentage from 0 to 100 with up to two decimal places."];
      if (!validRate(tax)) localErrors[`items.${index}.tax_rate`] = ["Enter a percentage from 0 to 100 with up to two decimal places."];
      return { description: item.description, quantity, unit_price_cents: price ?? 0, discount_rate: discount, tax_rate: tax };
    });
    if (!selectedCurrency) localErrors.currency_code = ["Choose a currency."];
    else if (!isSupportedCurrency(selectedCurrency)) localErrors.currency_code = ["Choose a supported currency."];
    if (Object.keys(localErrors).length) { setErrors(localErrors); return; }

    const payload: InvoicePayload = {
      client_id: Number(values.clientId),
      issue_date: values.issueDate,
      due_date: values.dueDate,
      notes: values.notes,
      items: itemPayloads,
      currency_code: selectedCurrency,
    };
    const unchangedClientPayload: Partial<InvoicePayload> = { ...payload };
    delete unchangedClientPayload.client_id;
    busyRef.current = true; setSaving(true);
    try {
      const response = invoice ? await api.updateInvoice(String(invoice.id), values.clientId === String(invoice.client_id) ? unchangedClientPayload : payload) : await api.createInvoice(payload);
      router.push(`/invoices/${response.data.id}`);
    } catch (cause) {
      if (cause instanceof ApiError) {
        setErrors(cause.errors);
        setFormError(cause.status === 422 && Object.keys(cause.errors).length ? (cause.errors.status ? { message: cause.errors.status[0], status: 422 } : null) : {
          message: cause.status === 401 ? "Your session has expired. Sign in again to continue." : cause.status === 403 ? "You do not have access to save this invoice." : cause.status >= 500 ? "The invoice could not be saved. Please try again." : cause.message,
          status: cause.status,
        });
      } else setFormError({ message: "The invoice could not be saved. Please try again." });
      busyRef.current = false; setSaving(false);
    }
  };

  const cancelHref = invoice ? `/invoices/${invoice.id}` : "/invoices";
  return <form className="client-form invoice-form" onSubmit={(event) => void submit(event)} noValidate>
    <div className="form-grid">
      <label className="client-field"><span>Client <b>*</b></span><select required value={values.clientId} onChange={(event) => update("clientId", event.target.value, "client_id")} disabled={clientLoading || Boolean(clientError) || saving} aria-invalid={Boolean(errors.client_id)} aria-describedby={errors.client_id ? "invoice-client-error" : undefined}>
        <option value="">{clientLoading ? "Loading clients..." : clientError ? "Clients unavailable" : "Select a client"}</option>
        {invoice && !clients.some((client) => client.id === invoice.client_id) && <option value={invoice.client_id}>{invoice.client.name} (unavailable)</option>}
        {clients.map((client) => <option key={client.id} value={client.id}>{client.name}{client.company ? ` — ${client.company}` : ""}</option>)}
      </select>{errors.client_id && <small id="invoice-client-error" className="field-error">{errors.client_id[0]}</small>}</label>
      <CurrencySelect value={selectedCurrency} onChange={(code) => update("currencyCode", code, "currency_code")} disabled={saving} idPrefix="invoice" error={errors.currency_code?.[0]} helpText={!invoice && workspace ? `Workspace default: ${workspace.default_currency.toUpperCase()}.` : "Choose the currency used on this invoice."} />
    </div>
    {clientError && <p className="field-error" role="alert">{clientError.message} {clientError.status === 401 ? <Link href={`/login?next=${encodeURIComponent(invoice ? `/invoices/${invoice.id}/edit` : "/invoices/new")}`} className="related-link">Sign in</Link> : <button type="button" className="text-button" onClick={() => { setClientError(null); setClientLoading(true); setClientRetry((value) => value + 1); }}>Retry clients</button>}</p>}
    {!clientLoading && !clientError && clients.length === 0 && <p className="invoice-field-help">You need a client before creating an invoice. <Link href="/clients/new" className="related-link">Create a client</Link>.</p>}
    <div className="form-grid">
      <label className="client-field"><span>Issue date <b>*</b></span><input required type="date" value={values.issueDate} onChange={(event) => update("issueDate", event.target.value, "issue_date")} disabled={saving} aria-invalid={Boolean(errors.issue_date)} aria-describedby={errors.issue_date ? "invoice-issue-date-error" : undefined} />{errors.issue_date && <small id="invoice-issue-date-error" className="field-error">{errors.issue_date[0]}</small>}</label>
      <label className="client-field"><span>Due date <b>*</b></span><input required type="date" value={values.dueDate} onChange={(event) => update("dueDate", event.target.value, "due_date")} disabled={saving} aria-invalid={Boolean(errors.due_date)} aria-describedby={errors.due_date ? "invoice-due-date-error" : undefined} />{errors.due_date && <small id="invoice-due-date-error" className="field-error">{errors.due_date[0]}</small>}</label>
    </div>
    <label className="client-field"><span>Notes</span><textarea rows={4} value={values.notes} onChange={(event) => update("notes", event.target.value, "notes")} disabled={saving} placeholder="Additional details for this invoice..." aria-invalid={Boolean(errors.notes)} aria-describedby={errors.notes ? "invoice-notes-error" : undefined} />{errors.notes && <small id="invoice-notes-error" className="field-error">{errors.notes[0]}</small>}</label>
    <InvoiceItemsEditor items={items} errors={errors} disabled={saving} onChange={updateItem} onAdd={addItem} onRemove={removeItem} />
    <p className="invoice-form-guidance">{invoice ? `Editing draft ${invoice.invoice_number}.` : "Invoice number will be generated automatically."} Final amounts are calculated when you save.</p>
    {formError && <div className="form-error" role="alert">{formError.message}{formError.status === 401 && <> <Link href={`/login?next=${encodeURIComponent(invoice ? `/invoices/${invoice.id}/edit` : "/invoices/new")}`}>Sign in</Link></>}</div>}
    <div className="form-actions"><Link href={cancelHref} className="secondary-button">Cancel</Link><button type="submit" className="primary-button form-submit" disabled={saving || clientLoading || Boolean(clientError) || clients.length === 0}>{saving ? "Saving..." : invoice ? "Save draft" : "Create draft"}<span aria-hidden="true">→</span></button></div>
  </form>;
}
