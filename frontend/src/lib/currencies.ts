export const currencyOptions = [
  { code: "EUR", label: "EUR — Euro (€)" },
  { code: "USD", label: "USD — US Dollar ($)" },
  { code: "GBP", label: "GBP — British Pound (£)" },
  { code: "CHF", label: "CHF — Swiss Franc (CHF)" },
  { code: "CAD", label: "CAD — Canadian Dollar (CA$)" },
  { code: "AUD", label: "AUD — Australian Dollar (A$)" },
] as const;

export function isSupportedCurrency(code: string): boolean {
  return currencyOptions.some((option) => option.code === code);
}
