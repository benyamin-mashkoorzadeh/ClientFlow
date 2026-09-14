import { currencyOptions, isSupportedCurrency } from "@/lib/currencies";

type CurrencySelectProps = {
  value: string;
  onChange: (code: string) => void;
  helpText: string;
  idPrefix: string;
  error?: string;
  disabled?: boolean;
};

export function CurrencySelect({ value, onChange, helpText, idPrefix, error, disabled }: CurrencySelectProps) {
  const helpId = `${idPrefix}-currency-help`;
  const errorId = `${idPrefix}-currency-error`;

  return <label className="client-field">
    <span>Currency <b>*</b></span>
    <select className="currency-select" name="currency_code" required value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled} aria-invalid={Boolean(error)} aria-describedby={`${helpId}${error ? ` ${errorId}` : ""}`}>
      <option value="" disabled>Select a currency</option>
      {value && !isSupportedCurrency(value) && <option value={value} disabled>{value} — Choose a supported currency</option>}
      {currencyOptions.map((option) => <option key={option.code} value={option.code}>{option.label}</option>)}
    </select>
    <small id={helpId} className="invoice-field-help">{helpText}</small>
    {error && <small id={errorId} className="field-error">{error}</small>}
  </label>;
}
