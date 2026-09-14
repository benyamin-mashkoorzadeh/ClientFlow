import type { TaskPriority } from "@/lib/types";

const labels: Record<TaskPriority, string> = { low: "Low", medium: "Medium", high: "High" };

export function TaskPriorityBadge({ priority }: { priority: TaskPriority }) { return <span className={`task-priority priority-label-${priority}`}><i />{labels[priority]}</span>; }