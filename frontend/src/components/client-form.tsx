"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Client, type ClientPayload } from "@/lib/types";

const emptyClient: ClientPayload = { name: "", company: "", email: "", phone: "", address: "", notes: "" };

export function ClientForm({ client }: { client?: Client }) {
  const router = useRouter();
  const [values, setValues] = useState<ClientPayload>(() => client ? { name: client.name, company: client.company ?? "", email: client.email ?? "", phone: client.phone ?? "", address: client.address ?? "", notes: client.notes ?? "" } : emptyClient);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const update = (field: keyof ClientPayload, value: string) => setValues((current) => ({ ...current, [field]: value }));
  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setSaving(true); setErrors({}); setFormError(null);
    try {
      const response = client ? await api.updateClient(String(client.id), values) : await api.createClient(values);
      router.push(`/clients/${response.data.id}`);
    } catch (cause) {
      if (cause instanceof ApiError) { setErrors(cause.errors); setFormError(cause.status === 422 ? null : cause.message); }
      else setFormError("The client could not be saved. Please try again.");
    } finally { setSaving(false); }
  };
  return <form className="client-form" onSubmit={submit} noValidate><div className="form-grid"><ClientField label="Name" required value={values.name} onChange={(value) => update("name", value)} error={errors.name?.[0]} /><ClientField label="Company" value={values.company ?? ""} onChange={(value) => update("company", value)} error={errors.company?.[0]} /><ClientField label="Email" type="email" value={values.email ?? ""} onChange={(value) => update("email", value)} error={errors.email?.[0]} /><ClientField label="Phone" type="tel" value={values.phone ?? ""} onChange={(value) => update("phone", value)} error={errors.phone?.[0]} /></div><ClientField label="Address" value={values.address ?? ""} onChange={(value) => update("address", value)} error={errors.address?.[0]} /><label className="client-field"><span>Notes</span><textarea value={values.notes ?? ""} onChange={(event) => update("notes", event.target.value)} rows={5} placeholder="Anything useful to remember..." />{errors.notes && <small className="field-error">{errors.notes[0]}</small>}</label>{formError && <div className="form-error" role="alert">{formError}</div>}<div className="form-actions"><Link href={client ? `/clients/${client.id}` : "/clients"} className="secondary-button">Cancel</Link><button className="primary-button form-submit" disabled={saving}>{saving ? "Saving..." : client ? "Save changes" : "Create client"}<span>→</span></button></div></form>;
}

function ClientField({ label, value, onChange, error, type = "text", required = false }: { label: string; value: string; onChange: (value: string) => void; error?: string; type?: string; required?: boolean }) { return <label className="client-field"><span>{label}{required && <b> *</b>}</span><input required={required} type={type} value={value} onChange={(event) => onChange(event.target.value)} />{error && <small className="field-error">{error}</small>}</label>; }