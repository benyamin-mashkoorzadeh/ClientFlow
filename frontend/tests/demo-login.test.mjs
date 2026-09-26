import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { test } from "node:test";
import ts from "typescript";

const require = createRequire(import.meta.url);
const source = readFileSync(new URL("../src/components/auth-form.tsx", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX },
}).outputText;

const demoUser = { id: 99, name: "ClientFlow Demo", email: "demo@clientflow.app", email_verified_at: "2026-09-26T12:00:00.000Z" };
const demoWorkspace = { id: 44, name: "Northstar Creative Studio", slug: "clientflow-demo", default_currency: "EUR" };

function deferred() {
  let resolve;
  const promise = new Promise((resolvePromise) => { resolve = resolvePromise; });
  return { promise, resolve };
}

function findAll(node, predicate) {
  if (Array.isArray(node)) return node.flatMap((child) => findAll(child, predicate));
  if (!node || typeof node !== "object") return [];
  return [...(predicate(node) ? [node] : []), ...findAll(node.props?.children, predicate)];
}

function createHarness({ demo = async () => ({ user: demoUser, workspace: demoWorkspace }) } = {}) {
  class ApiError extends Error {
    constructor(message, status = 500, errors = {}) {
      super(message);
      this.status = status;
      this.errors = errors;
    }
  }

  const state = [];
  const refs = [];
  const routes = [];
  let stateIndex = 0;
  let refIndex = 0;
  let demoCalls = 0;
  let authenticated = null;

  const react = {
    useState(initial) {
      const index = stateIndex++;
      if (!(index in state)) state[index] = initial;
      return [state[index], (value) => { state[index] = typeof value === "function" ? value(state[index]) : value; }];
    },
    useRef(initial) {
      const index = refIndex++;
      if (!(index in refs)) refs[index] = { current: initial };
      return refs[index];
    },
  };
  const element = (type, props, key) => ({ type, props: props ?? {}, key });
  const modules = {
    "react": react,
    "react/jsx-runtime": { jsx: element, jsxs: element },
    "next/link": { default: (props) => element("a", props) },
    "next/navigation": {
      useRouter: () => ({ replace: (path) => routes.push(path) }),
      useSearchParams: () => ({ get: () => null }),
    },
    "@/context/auth-context": { useAuth: () => ({
      login: async () => {},
      demoLogin: async () => {
        demoCalls += 1;
        const response = await demo({ ApiError });
        authenticated = response;
      },
      register: async () => {},
    }) },
    "@/lib/types": { ApiError },
  };
  const loaded = { exports: {} };
  new Function("require", "module", "exports", javascript)((name) => modules[name] ?? require(name), loaded, loaded.exports);

  return {
    ApiError,
    render() {
      stateIndex = 0;
      refIndex = 0;
      return loaded.exports.LoginForm();
    },
    routes,
    demoCalls: () => demoCalls,
    authenticated: () => authenticated,
  };
}

function demoButton(tree) {
  return findAll(tree, (node) => node.type === "button" && ["Explore demo", "Opening demo..."].includes(node.props.children?.[0]))[0];
}

async function settle() {
  await new Promise((resolve) => setImmediate(resolve));
}

test("Login shows an Explore demo secondary action", () => {
  const harness = createHarness();
  const action = demoButton(harness.render());

  assert.ok(action);
  assert.equal(action.props.type, "button");
  assert.equal(action.props.className, "demo-button");
});

test("Explore demo authenticates through context and navigates to the dashboard", async () => {
  const harness = createHarness();
  demoButton(harness.render()).props.onClick();
  await settle();

  assert.equal(harness.demoCalls(), 1);
  assert.deepEqual(harness.authenticated(), { user: demoUser, workspace: demoWorkspace });
  assert.deepEqual(harness.routes, ["/dashboard"]);
});

test("a demo login failure stays on Login and displays the backend error", async () => {
  const harness = createHarness({ demo: async ({ ApiError }) => { throw new ApiError("The demo workspace is not available right now.", 503); } });
  demoButton(harness.render()).props.onClick();
  await settle();

  const tree = harness.render();
  assert.deepEqual(harness.routes, []);
  assert.equal(findAll(tree, (node) => node.props?.role === "alert")[0].props.children, "The demo workspace is not available right now.");
});

test("the demo action disables submissions and ignores duplicate clicks while loading", async () => {
  const pending = deferred();
  const harness = createHarness({ demo: () => pending.promise });
  const initial = harness.render();
  demoButton(initial).props.onClick();
  demoButton(initial).props.onClick();

  const loading = harness.render();
  assert.equal(harness.demoCalls(), 1);
  assert.equal(demoButton(loading).props.disabled, true);
  assert.equal(findAll(loading, (node) => node.type === "button").every((node) => node.props.disabled), true);

  pending.resolve({ user: demoUser, workspace: demoWorkspace });
  await settle();
});
