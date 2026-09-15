import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { test } from "node:test";
import ts from "typescript";

const require = createRequire(import.meta.url);
const source = readFileSync(new URL("../src/components/email-verification-notice.tsx", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX },
}).outputText;

const unverifiedUser = { id: 1, name: "Test User", email: "test@example.com", email_verified_at: null };
const verifiedUser = { ...unverifiedUser, email_verified_at: "2026-09-15T12:00:00.000Z" };
const workspace = { id: 1, name: "Test Workspace", slug: "test-workspace", default_currency: "EUR" };

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });
  return { promise, resolve, reject };
}

function findAll(node, predicate) {
  if (Array.isArray(node)) return node.flatMap((child) => findAll(child, predicate));
  if (!node || typeof node !== "object") return [];
  return [...(predicate(node) ? [node] : []), ...findAll(node.props?.children, predicate)];
}

function createHarness({ refresh = async () => ({ user: unverifiedUser, workspace }), resend = async () => ({ message: "Sent" }) } = {}) {
  const state = [];
  const refs = [];
  const routes = [];
  const auth = { user: unverifiedUser };
  let stateIndex = 0;
  let refIndex = 0;
  let refreshCalls = 0;
  let resendCalls = 0;

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
    "next/navigation": { useRouter: () => ({ replace: (path) => routes.push(path) }) },
    "@/context/auth-context": { useAuth: () => ({
      user: auth.user,
      refreshUser: async () => {
        refreshCalls += 1;
        const response = await refresh();
        auth.user = response.user;
        return response;
      },
      logout: async () => {},
    }) },
    "@/lib/api": { api: { resendVerification: async () => { resendCalls += 1; return resend(); } } },
    "@/lib/types": { ApiError: class ApiError extends Error {} },
    "@/components/brand-logo": { BrandLogo: () => null },
    "@/components/theme-toggle": { ThemeToggle: () => null },
  };
  const loaded = { exports: {} };
  new Function("require", "module", "exports", javascript)((name) => modules[name] ?? require(name), loaded, loaded.exports);

  return {
    render() {
      stateIndex = 0;
      refIndex = 0;
      return loaded.exports.EmailVerificationNotice();
    },
    routes,
    refreshCalls: () => refreshCalls,
    resendCalls: () => resendCalls,
  };
}

function button(tree, label) {
  return findAll(tree, (node) => node.type === "button" && node.props.children === label)[0];
}

async function settle() {
  await new Promise((resolve) => setImmediate(resolve));
}

test("I've verified refreshes the backend user, updates auth state, and replaces the route when verified", async () => {
  const harness = createHarness({ refresh: async () => ({ user: verifiedUser, workspace }) });
  button(harness.render(), "I’ve verified").props.onClick();
  await settle();

  assert.equal(harness.refreshCalls(), 1);
  assert.deepEqual(harness.routes, ["/dashboard"]);
  assert.equal(harness.render(), null);
});

test("an unverified fresh user remains on the verification screen with guidance", async () => {
  const harness = createHarness();
  button(harness.render(), "I’ve verified").props.onClick();
  await settle();

  const tree = harness.render();
  assert.equal(harness.refreshCalls(), 1);
  assert.deepEqual(harness.routes, []);
  assert.equal(findAll(tree, (node) => node.props?.role === "status")[0].props.children, "Your email hasn't been verified yet. Please use the verification link we sent to your email.");
});

test("a failed refresh remains on the verification screen and shows an error", async () => {
  const harness = createHarness({ refresh: async () => { throw new Error("Network failure"); } });
  button(harness.render(), "I’ve verified").props.onClick();
  await settle();

  const tree = harness.render();
  assert.deepEqual(harness.routes, []);
  assert.equal(findAll(tree, (node) => node.props?.role === "alert")[0].props.children, "This action could not be completed. Please try again.");
});

test("the verification check disables actions and ignores duplicate clicks", async () => {
  const pendingRefresh = deferred();
  const harness = createHarness({ refresh: () => pendingRefresh.promise });
  const initial = harness.render();
  button(initial, "I’ve verified").props.onClick();
  button(initial, "I’ve verified").props.onClick();

  assert.equal(harness.refreshCalls(), 1);
  assert.equal(findAll(harness.render(), (node) => node.type === "button").every((node) => node.props.disabled), true);

  pendingRefresh.resolve({ user: unverifiedUser, workspace });
  await settle();
});

test("resending verification remains a separate working action", async () => {
  const harness = createHarness();
  button(harness.render(), "Resend link").props.onClick();
  await settle();

  const tree = harness.render();
  assert.equal(harness.resendCalls(), 1);
  assert.equal(harness.refreshCalls(), 0);
  assert.equal(findAll(tree, (node) => node.props?.role === "status")[0].props.children, "Verification link sent. Check your inbox and spam folder.");
});
