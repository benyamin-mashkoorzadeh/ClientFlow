"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api } from "@/lib/api";
import { ApiError } from "@/lib/types";

export function ProjectDeleteAction({ id, compact = false }: { id: number; compact?: boolean }) {
  const router = useRouter(); const [busy, setBusy] = useState(false); const [error, setError] = useState<string | null>(null);
  const remove = async () => { if (!window.confirm("Delete this project? It will disappear from normal views.")) return; setBusy(true); setError(null); try { await api.deleteProject(String(id)); router.push("/projects"); router.refresh(); } catch (cause) { setError(cause instanceof ApiError ? cause.message : "The project could not be deleted."); setBusy(false); } };
  return <>{error && <span className="inline-error">{error}</span>}<button className={compact ? "table-action table-action-danger" : "danger-button"} onClick={() => void remove()} disabled={busy}>{busy ? "Deleting..." : compact ? "Delete" : "Delete project"}</button></>;
}