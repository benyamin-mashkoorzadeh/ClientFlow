"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { ApiError } from "@/lib/types";

export function ClientDeleteAction({ id, compact = false }: { id: number; compact?: boolean }) {
  const router = useRouter(); const [busy, setBusy] = useState(false); const [error, setError] = useState<string | null>(null);
  const remove = async () => { if (!window.confirm("Delete this client? This removes the client from normal views.")) return; setBusy(true); setError(null); try { await api.deleteClient(String(id)); router.push("/clients"); router.refresh(); } catch (cause) { setError(cause instanceof ApiError ? cause.message : "The client could not be deleted."); setBusy(false); } };
  return <>{error && <span className="inline-error">{error}</span>}<button className={compact ? "table-action table-action-danger" : "danger-button"} onClick={() => void remove()} disabled={busy}>{busy ? "Deleting..." : compact ? "Delete" : "Delete client"}</button></>;
}