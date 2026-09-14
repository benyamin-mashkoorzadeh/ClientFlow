export function formatInvoiceDate(date: string): string {
  return new Date(`${date}T00:00:00`).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export function formatInvoiceRate(rate: string): string {
  return `${rate.replace(/\.00$/, "").replace(/(\.\d)0$/, "$1")}%`;
}
