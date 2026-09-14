"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { ApiError } from "@/lib/types";

export function TaskDeleteAction({ id, compact = false }: { id: number; compact?: boolean }) {
  const router = useRouter(); const [busy, setBusy] = useState(false); const [error, setError] = useState<string | null>(null);
  const remove = async () => { if (!window.confirm("Delete this task? It will disappear from normal views.")) return; setBusy(true); setError(null); try { await api.deleteTask(String(id)); router.push("/tasks"); router.refresh(); } catch (cause) { setError(cause instanceof ApiError ? cause.message : "The task could not be deleted."); setBusy(false); } };
  return <>{error && <span className="inline-error">{error}</span>}<button className={compact ? "table-action table-action-danger" : "danger-button"} onClick={() => void remove()} disabled={busy}>{busy ? "Deleting..." : compact ? "Delete" : "Delete task"}</button></>;
}