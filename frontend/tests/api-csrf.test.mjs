import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { test } from "node:test";
import ts from "typescript";

const require = createRequire(import.meta.url);
const source = readFileSync(new URL("../src/lib/api.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;

function loadApi(fetchRequest, document) {
  class ApiError extends Error {
    constructor(message, status, errors = {}) {
      super(message);
      this.status = status;
      this.errors = errors;
    }
  }

  const compiledModule = { exports: {} };
  const run = new Function("require", "module", "exports", "fetch", "document", "Headers", "process", "window", "Event", javascript);
  run((name) => name === "@/lib/types" ? { ApiError } : require(name), compiledModule, compiledModule.exports, fetchRequest, document, Headers, { env: { NEXT_PUBLIC_API_URL: "http://localhost:8000" } }, { dispatchEvent() {} }, Event);
  return { api: compiledModule.exports.api, ApiError };
}

const json = (data) => new Response(JSON.stringify(data), { status: 200, headers: { "Content-Type": "application/json" } });

test("the first mutation after login sends the rotated token without another CSRF fetch", async () => {
  const document = { cookie: "XSRF-TOKEN=old-token" };
  const calls = [];
  const { api } = loadApi(async (url, init) => {
    calls.push({ url, init });
    if (url.endsWith("/sanctum/csrf-cookie")) {
      document.cookie = "XSRF-TOKEN=bootstrap-token";
      return new Response(null, { status: 204 });
    }
    if (url.endsWith("/api/login")) {
      assert.equal(init.headers.get("X-XSRF-TOKEN"), "bootstrap-token");
      document.cookie = "XSRF-TOKEN=rotated-token";
      return json({ user: { id: 1 }, workspace: { id: 1 } });
    }
    assert.equal(init.method, "PATCH");
    assert.equal(init.headers.get("X-XSRF-TOKEN"), "rotated-token");
    assert.equal(init.credentials, "include");
    return json({ data: { id: 1 } });
  }, document);

  await api.login({ email: "test@example.com", password: "secret" });
  await api.updateClient("1", { name: "Updated" });
  assert.deepEqual(calls.map(({ url }) => new URL(url).pathname), ["/sanctum/csrf-cookie", "/api/login", "/api/clients/1"]);
});

test("a missing CSRF cookie is initialized before a mutation", async () => {
  const document = { cookie: "" };
  const calls = [];
  const { api } = loadApi(async (url, init) => {
    calls.push(url);
    if (url.endsWith("/sanctum/csrf-cookie")) {
      assert.equal(init.credentials, "include");
      document.cookie = "XSRF-TOKEN=fresh%2Btoken";
      return new Response(null, { status: 204 });
    }
    assert.equal(init.headers.get("X-XSRF-TOKEN"), "fresh+token");
    return json({ data: { id: 1 } });
  }, document);

  await api.updateClient("1", { name: "Updated" });
  assert.deepEqual(calls.map((url) => new URL(url).pathname), ["/sanctum/csrf-cookie", "/api/clients/1"]);
});

test("a failed CSRF check does not retry an unsafe mutation", async () => {
  const document = { cookie: "XSRF-TOKEN=stale-token" };
  let requests = 0;
  const { api, ApiError } = loadApi(async () => {
    requests += 1;
    return new Response(JSON.stringify({ message: "CSRF token mismatch." }), { status: 419, headers: { "Content-Type": "application/json" } });
  }, document);

  await assert.rejects(api.updateClient("1", { name: "Updated" }), (error) => error instanceof ApiError && error.status === 419);
  assert.equal(requests, 1);
});
