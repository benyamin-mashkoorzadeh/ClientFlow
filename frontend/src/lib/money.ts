export function centsToInput(cents: number | null): string {
  if (cents === null) return "";
  const sign = cents < 0 ? "-" : "";
  const digits = String(Math.abs(cents)).padStart(3, "0");
  return `${sign}${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

export function inputToCents(value: string): number | null {
  const normalized = value.trim();
  if (!normalized) return null;
  if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) return null;
  const [whole, fraction = ""] = normalized.split(".");
  const cents = Number(`${whole}${fraction.padEnd(2, "0")}`);
  return Number.isSafeInteger(cents) ? cents : null;
}

export function formatCents(cents: number | null, currency: string | null): string { return cents === null ? "Not set" : new Intl.NumberFormat("en-US", { style: "currency", currency: currency || "USD" }).format(cents / 100); }
