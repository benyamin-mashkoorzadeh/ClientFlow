export function isTaskOverdue(deadline: string | null, status: string): boolean { const today = new Date(); const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`; return Boolean(deadline && deadline < localDate && status !== "done"); }

export function formatTaskDate(deadline: string | null): string { return deadline ? new Date(`${deadline}T00:00:00`).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) : "No deadline"; }
