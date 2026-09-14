import type { TaskStatus } from "@/lib/types";

const labels: Record<TaskStatus, string> = { todo: "To do", in_progress: "In progress", done: "Done" };

export function TaskStatusBadge({ status }: { status: TaskStatus }) { return <span className={`project-status task-status-${status}`}><i />{labels[status]}</span>; }