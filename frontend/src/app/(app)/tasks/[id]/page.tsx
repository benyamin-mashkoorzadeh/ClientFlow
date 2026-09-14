"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Task } from "@/lib/types";
import { formatTaskDate, isTaskOverdue } from "@/lib/task-utils";
import { PageHeader } from "@/components/page-header";
import { TaskDeleteAction } from "@/components/task-delete-action";
import { TaskPriorityBadge } from "@/components/task-priority-badge";
import { TaskStatusBadge } from "@/components/task-status-badge";

export default function TaskDetailPage() { const { id } = useParams<{ id: string }>(); const [task, setTask] = useState<Task | null>(null); const [error, setError] = useState<string | null>(null); const [missing, setMissing] = useState(false); useEffect(() => { void api.task(id).then((response) => setTask(response.data)).catch((cause: unknown) => { if (cause instanceof ApiError && cause.status === 404) setMissing(true); else setError(cause instanceof ApiError ? cause.message : "The task could not be loaded."); }); }, [id]); if (!task && !error && !missing) return <div className="screen-state"><span className="spinner" />Loading task...</div>; if (missing) return <div className="notice notice-warning"><strong>Task not found</strong><span>This task may have been deleted or you may not have access to it.</span><Link href="/tasks" className="text-button">Back to tasks</Link></div>; if (error || !task) return <div className="notice notice-warning"><strong>Couldn’t load task</strong><span>{error ?? "Unexpected task response."}</span><button className="text-button" onClick={() => window.location.reload()}>Try again</button></div>; const overdue = isTaskOverdue(task.deadline, task.status); return <div><Link href="/tasks" className="back-link">← Back to tasks</Link><PageHeader eyebrow="Task detail" title={task.title} description={task.project.name} /><div className="detail-layout"><section className="panel client-detail-card"><div className="project-detail-top"><div className="badge-stack"><TaskStatusBadge status={task.status} /><TaskPriorityBadge priority={task.priority} />{overdue && <span className="overdue-badge">Overdue</span>}</div><div className="detail-actions"><Link href={`/tasks/${task.id}/edit`} className="secondary-button">Edit task</Link><TaskDeleteAction id={task.id} /></div></div><div className="detail-fields"><DetailField label="Project" value={task.project.name} href={`/projects/${task.project_id}`} /><DetailField label="Deadline" value={formatTaskDate(task.deadline)} wide={overdue} /><DetailField label="Description" value={task.description} wide /></div></section></div></div>; }

function DetailField({ label, value, href, wide = false }: { label: string; value: string | null; href?: string; wide?: boolean }) { return <div className={`detail-field ${wide ? "detail-field-wide" : ""}`}><span>{label}</span>{href && value ? <Link href={href}>{value}</Link> : <p>{value || "Not provided"}</p>}</div>; }