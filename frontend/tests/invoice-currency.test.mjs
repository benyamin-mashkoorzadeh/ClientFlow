import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { test } from "node:test";
import ts from "typescript";

const nodeRequire = createRequire(import.meta.url);
const compile = (path, jsx) => ts.transpileModule(readFileSync(new URL(path, import.meta.url), "utf8"), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx },
}).outputText;

const currencyModule = { exports: {} };
new Function("module", "exports", compile("../src/lib/currencies.ts"))(currencyModule, currencyModule.exports);
const currencies = currencyModule.exports;
const invoiceFormCode = compile("../src/components/invoice-form.tsx", ts.JsxEmit.ReactJSX);
const projectFormCode = compile("../src/components/project-form.tsx", ts.JsxEmit.ReactJSX);
const currencySelectCode = compile("../src/components/currency-select.tsx", ts.JsxEmit.ReactJSX);

function formTree({ workspace = null, invoice, project, state = {} } = {}) {
  let stateIndex = 0;
  let submittedPayload;
  const element = (type, props, key) => ({ type, props: props ?? {}, key });
  const react = {
    useState(initial) {
      const index = stateIndex++;
      return [Object.hasOwn(state, index) ? state[index] : typeof initial === "function" ? initial() : initial, () => {}];
    },
    useRef(initial) { return { current: initial }; },
    useEffect() {},
  };
  const api = {
    clients: async () => ({ data: [], meta: { last_page: 1 } }),
    createInvoice: async (payload) => { submittedPayload = payload; return { data: { id: 1 } }; },
    updateInvoice: async (_id, payload) => { submittedPayload = payload; return { data: { id: 1 } }; },
    createProject: async (payload) => { submittedPayload = payload; return { data: { id: 1 } }; },
    updateProject: async (_id, payload) => { submittedPayload = payload; return { data: { id: 1 } }; },
  };
  const modules = {
    "react": react,
    "react/jsx-runtime": { jsx: element, jsxs: element },
    "next/link": { default: () => null },
    "next/navigation": { useRouter: () => ({ push() {} }) },
    "@/lib/api": { api },
    "@/lib/currencies": currencies,
    "@/lib/money": { centsToInput: (value) => String(value / 100), inputToCents: (value) => Number(value) * 100 },
    "@/lib/types": { ApiError: class ApiError extends Error {} },
    "@/context/auth-context": { useAuth: () => ({ workspace }) },
    "@/components/invoice-items-editor": { InvoiceItemsEditor: () => null },
  };
  const selectModule = { exports: {} };
  new Function("require", "module", "exports", currencySelectCode)((name) => modules[name] ?? nodeRequire(name), selectModule, selectModule.exports);
  modules["@/components/currency-select"] = selectModule.exports;
  const loaded = { exports: {} };
  new Function("require", "module", "exports", project === undefined ? invoiceFormCode : projectFormCode)((name) => modules[name] ?? nodeRequire(name), loaded, loaded.exports);
  const tree = project === undefined ? loaded.exports.InvoiceForm({ invoice }) : loaded.exports.ProjectForm({ project: project || undefined });
  return { tree, submittedPayload: () => submittedPayload, CurrencySelect: selectModule.exports.CurrencySelect };
}

function findAll(node, predicate) {
  if (Array.isArray(node)) return node.flatMap((child) => findAll(child, predicate));
  if (!node || typeof node !== "object") return [];
  return [...(predicate(node) ? [node] : []), ...findAll(typeof node.type === "function" ? node.type(node.props) : node.props?.children, predicate)];
}

function currencySelect(tree) {
  return findAll(tree, (node) => node.type === "select").find((node) =>
    String(node.props["aria-describedby"] ?? "").includes("currency-help"));
}

test("invoice create preselects the workspace default and offers readable ISO options", () => {
  const { tree, CurrencySelect } = formTree({ workspace: { default_currency: "EUR" } });
  const select = currencySelect(tree);
  assert.equal(findAll(tree, (node) => node.type === CurrencySelect).length, 1);
  assert.equal(select.props.value, "EUR");
  assert.equal(select.props.required, true);
  const options = findAll(select, (node) => node.type === "option" && node.props.value);
  assert.deepEqual(options.map((option) => option.props.value), ["EUR", "USD", "GBP", "CHF", "CAD", "AUD"]);
  assert.equal(options[0].props.children, "EUR — Euro (€)");
});

test("invoice edit preselects the saved currency over the workspace default", () => {
  const invoice = { id: 4, client_id: 1, currency_code: "GBP", issue_date: "2026-09-14", due_date: "2026-09-30", notes: null, invoice_number: "INV-0004", client: { id: 1, name: "Client" },
    items: [{ description: "Work", quantity: 1, unit_price_cents: 1000, discount_rate: "0", tax_rate: "0" }] };
  const { tree, CurrencySelect } = formTree({ workspace: { default_currency: "EUR" }, invoice });
  assert.equal(findAll(tree, (node) => node.type === CurrencySelect).length, 1);
  assert.equal(currencySelect(tree).props.value, "GBP");
});

test("invoice edit sends the saved ISO currency code", async () => {
  const invoice = { id: 4, client_id: 1, currency_code: "GBP", issue_date: "2026-09-14", due_date: "2026-09-30", notes: null, invoice_number: "INV-0004", client: { id: 1, name: "Client" },
    items: [{ description: "Work", quantity: 1, unit_price_cents: 1000, discount_rate: "0", tax_rate: "0" }] };
  const { tree, submittedPayload } = formTree({ workspace: { default_currency: "EUR" }, invoice, state: {
    2: [{ id: 1, name: "Client" }],
    3: false,
  } });
  await tree.props.onSubmit({ preventDefault() {} });
  assert.equal(submittedPayload().currency_code, "GBP");
});

test("invoice form submits the selected ISO code, not its display label", async () => {
  const { tree, submittedPayload } = formTree({ workspace: { default_currency: "EUR" }, state: {
    0: { clientId: "1", issueDate: "2026-09-14", dueDate: "2026-09-30", currencyCode: "CAD", notes: "" },
    1: [{ key: 0, description: "Work", quantity: "1", unitPrice: "10", discountRate: "0", taxRate: "0" }],
    2: [{ id: 1, name: "Client" }],
    3: false,
  } });
  await tree.props.onSubmit({ preventDefault() {} });
  assert.equal(submittedPayload().currency_code, "CAD");
});

test("project create and edit use the shared selector with the correct default and saved code", () => {
  const workspace = { default_currency: "EUR" };
  const created = formTree({ workspace, project: null });
  assert.equal(currencySelect(created.tree).props.value, "EUR");
  assert.equal(findAll(created.tree, (node) => node.type === created.CurrencySelect).length, 1);

  const project = { id: 5, client_id: 1, currency_code: "CHF", name: "Work", description: null, status: "active", start_date: null, end_date: null, budget_cents: null, client: { id: 1, name: "Client" } };
  const edited = formTree({ workspace, project });
  assert.equal(currencySelect(edited.tree).props.value, "CHF");
  assert.equal(findAll(edited.tree, (node) => node.type === edited.CurrencySelect).length, 1);
});

test("project create and edit submit ISO codes from the shared selector", async () => {
  const workspace = { default_currency: "EUR" };
  const created = formTree({ workspace, project: null, state: {
    0: { client_id: "1", name: "Work", description: "", status: "active", start_date: "", end_date: "", budget: "", currency_code: "AUD" },
  } });
  await created.tree.props.onSubmit({ preventDefault() {} });
  assert.equal(created.submittedPayload().currency_code, "AUD");

  const project = { id: 5, client_id: 1, currency_code: "CHF", name: "Work", description: null, status: "active", start_date: null, end_date: null, budget_cents: null, client: { id: 1, name: "Client" } };
  const edited = formTree({ workspace, project, state: {
    0: { client_id: "1", name: "Work", description: "", status: "active", start_date: "", end_date: "", budget: "", currency_code: "CAD" },
  } });
  await edited.tree.props.onSubmit({ preventDefault() {} });
  assert.equal(edited.submittedPayload().currency_code, "CAD");
});
