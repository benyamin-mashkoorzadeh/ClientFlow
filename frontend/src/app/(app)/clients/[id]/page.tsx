"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Client } from "@/lib/types";
import { ClientDeleteAction } from "@/components/client-delete-action";
import { PageHeader } from "@/components/page-header";

export default function ClientDetailPage() {
  const { id } = useParams<{ id: string }>(); const [client, setClient] = useState<Client | null>(null); const [error, setError] = useState<string | null>(null); const [missing, setMissing] = useState(false);
  useEffect(() => { void api.client(id).then((response) => setClient(response.data)).catch((cause: unknown) => { if (cause instanceof ApiError && cause.status === 404) setMissing(true); else setError(cause instanceof ApiError ? cause.message : "The client could not be loaded."); }); }, [id]);
  if (!client && !error && !missing) return <div className="screen-state"><span className="spinner" />Loading client...</div>;
  if (missing) return <div className="notice notice-warning"><strong>Client not found</strong><span>This client may have been deleted or you may not have access to it.</span><Link href="/clients" className="text-button">Back to clients</Link></div>;
  if (error || !client) return <div className="notice notice-warning"><strong>Couldn’t load client</strong><span>{error ?? "Unexpected client response."}</span><button className="text-button" onClick={() => window.location.reload()}>Try again</button></div>;
  return <div><Link href="/clients" className="back-link">← Back to clients</Link><PageHeader eyebrow="Client profile" title={client.name} description={client.company || "Independent client"} /><div className="detail-layout"><section className="panel client-detail-card"><div className="detail-avatar">{client.name.charAt(0).toUpperCase()}</div><div className="detail-actions"><Link href={`/clients/${client.id}/edit`} className="secondary-button">Edit client</Link><ClientDeleteAction id={client.id} /></div><div className="detail-fields"><DetailField label="Company" value={client.company} /><DetailField label="Email" value={client.email} href={client.email ? `mailto:${client.email}` : undefined} /><DetailField label="Phone" value={client.phone} href={client.phone ? `tel:${client.phone}` : undefined} /><DetailField label="Address" value={client.address} /><DetailField label="Notes" value={client.notes} wide /></div></section></div></div>;
}

function DetailField({ label, value, href, wide = false }: { label: string; value: string | null; href?: string; wide?: boolean }) { return <div className={`detail-field ${wide ? "detail-field-wide" : ""}`}><span>{label}</span>{href && value ? <a href={href}>{value}</a> : <p>{value || "Not provided"}</p>}</div>; }