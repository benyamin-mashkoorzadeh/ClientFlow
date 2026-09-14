"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Client } from "@/lib/types";
import { ClientForm } from "@/components/client-form";
import { PageHeader } from "@/components/page-header";

export default function EditClientPage() {
  const { id } = useParams<{ id: string }>(); const [client, setClient] = useState<Client | null>(null); const [error, setError] = useState<string | null>(null);
  useEffect(() => { void api.client(id).then((response) => setClient(response.data)).catch((cause: unknown) => setError(cause instanceof ApiError ? cause.message : "The client could not be loaded.")); }, [id]);
  if (error) return <div className="notice notice-warning"><strong>Couldn’t load client</strong><span>{error}</span><Link href={`/clients/${id}`} className="text-button">Back to client</Link></div>;
  if (!client) return <div className="screen-state"><span className="spinner" />Loading client...</div>;
  return <><PageHeader eyebrow="Client profile / Edit" title="Edit client" description={`Update the details for ${client.name}.`} /><section className="form-panel"><ClientForm client={client} /></section></>;
}