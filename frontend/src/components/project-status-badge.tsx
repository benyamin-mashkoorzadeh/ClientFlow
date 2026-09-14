import type { ProjectStatus } from "@/lib/types";

const labels: Record<ProjectStatus, string> = { active: "Active", completed: "Completed", on_hold: "On hold" };

export function ProjectStatusBadge({ status }: { status: ProjectStatus }) { return <span className={`project-status status-${status}`}><i />{labels[status]}</span>; }