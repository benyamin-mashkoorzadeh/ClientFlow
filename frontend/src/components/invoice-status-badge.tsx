import type { InvoiceStatus } from "@/lib/types";

const labels: Record<InvoiceStatus, string> = {
  draft: "Draft",
  sent: "Sent",
  paid: "Paid",
  cancelled: "Cancelled",
};

export function InvoiceStatusBadge({ status }: { status: InvoiceStatus }) {
  return <span className={`project-status invoice-status-${status}`}><i aria-hidden="true" />{labels[status]}</span>;
}
