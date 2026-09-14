"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { ApiError, type Task } from "@/lib/types";
import { PageHeader } from "@/components/page-header";
import { TaskForm } from "@/components/task-form";

export default function EditTaskPage() { const { id } = useParams<{ id: string }>(); const [task, setTask] = useState<Task | null>(null); const [error, setError] = useState<string | null>(null); useEffect(() => { void api.task(id).then((response) => setTask(response.data)).catch((cause: unknown) => setError(cause instanceof ApiError ? cause.message : "The task could not be loaded.")); }, [id]); if (error) return <div className="notice notice-warning"><strong>Couldn’t load task</strong><span>{error}</span><Link href={`/tasks/${id}`} className="text-button">Back to task</Link></div>; if (!task) return <div className="screen-state"><span className="spinner" />Loading task...</div>; return <><PageHeader eyebrow="Task detail / Edit" title="Edit task" description={`Update the details for ${task.title}.`} /><section className="form-panel"><TaskForm task={task} /></section></>; }