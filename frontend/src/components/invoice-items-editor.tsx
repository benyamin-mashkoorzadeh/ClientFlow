import type { ApiValidationErrors } from "@/lib/types";

export type InvoiceItemDraft = {
  key: number;
  description: string;
  quantity: string;
  unitPrice: string;
  discountRate: string;
  taxRate: string;
};

export function InvoiceItemsEditor({ items, errors, disabled, onChange, onAdd, onRemove }: {
  items: InvoiceItemDraft[];
  errors: ApiValidationErrors;
  disabled: boolean;
  onChange: (index: number, field: keyof Omit<InvoiceItemDraft, "key">, value: string) => void;
  onAdd: () => void;
  onRemove: (index: number) => void;
}) {
  return <section className="invoice-builder-items" aria-labelledby="invoice-builder-items-heading">
    <div className="invoice-builder-heading"><div><p className="eyebrow">Billed work</p><h2 id="invoice-builder-items-heading">Line items</h2></div><button type="button" className="secondary-button" onClick={onAdd} disabled={disabled}>Add item</button></div>
    {errors.items && <p className="field-error" role="alert">{errors.items[0]}</p>}
    <div className="invoice-builder-list">{items.map((item, index) => <fieldset className="invoice-item-card" key={item.key} disabled={disabled}>
      <legend>Item {index + 1}</legend>
      <div className="invoice-item-fields">
        <ItemField label="Description" required value={item.description} onChange={(value) => onChange(index, "description", value)} error={errors[`items.${index}.description`]?.[0]} errorId={`invoice-item-${item.key}-description-error`} placeholder="Work or service" maxLength={255} className="invoice-item-description" />
        <ItemField label="Quantity" required type="number" min="1" step="1" inputMode="numeric" value={item.quantity} onChange={(value) => onChange(index, "quantity", value)} error={errors[`items.${index}.quantity`]?.[0]} errorId={`invoice-item-${item.key}-quantity-error`} placeholder="1" />
        <ItemField label="Unit price" required inputMode="decimal" value={item.unitPrice} onChange={(value) => onChange(index, "unitPrice", value)} error={errors[`items.${index}.unit_price_cents`]?.[0]} errorId={`invoice-item-${item.key}-price-error`} placeholder="125.50" />
        <ItemField label="Discount %" inputMode="decimal" value={item.discountRate} onChange={(value) => onChange(index, "discountRate", value)} error={errors[`items.${index}.discount_rate`]?.[0]} errorId={`invoice-item-${item.key}-discount-error`} placeholder="0" />
        <ItemField label="Tax %" inputMode="decimal" value={item.taxRate} onChange={(value) => onChange(index, "taxRate", value)} error={errors[`items.${index}.tax_rate`]?.[0]} errorId={`invoice-item-${item.key}-tax-error`} placeholder="0" />
      </div>
      <button type="button" className="invoice-remove-item" onClick={() => onRemove(index)} disabled={disabled || items.length === 1} aria-label={`Remove item ${index + 1}`}>Remove item</button>
    </fieldset>)}</div>
  </section>;
}

function ItemField({ label, value, onChange, error, errorId, required = false, type = "text", inputMode, min, step, placeholder, maxLength, className = "" }: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  errorId: string;
  required?: boolean;
  type?: string;
  inputMode?: "decimal" | "numeric";
  min?: string;
  step?: string;
  placeholder?: string;
  maxLength?: number;
  className?: string;
}) {
  return <label className={`client-field invoice-item-field ${className}`}><span>{label}{required && <b> *</b>}</span><input required={required} type={type} inputMode={inputMode} min={min} step={step} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} maxLength={maxLength} aria-invalid={Boolean(error)} aria-describedby={error ? errorId : undefined} />{error && <small id={errorId} className="field-error">{error}</small>}</label>;
}
