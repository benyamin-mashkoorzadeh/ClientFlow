import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

const styles = readFileSync(new URL("../src/app/globals.css", import.meta.url), "utf8");

test("desktop auth layout gives only the register panel internal overflow", () => {
  const basePanel = styles.match(/\.auth-panel \{[^}]*\}/)?.[0] ?? "";

  assert.match(styles, /\.auth-page \{[^}]*height: 100dvh;[^}]*min-height: 0;[^}]*overflow: hidden;/);
  assert.match(basePanel, /height: 100%;[^}]*min-height: 0;[^}]*padding: min\(8vh, 55px\) 8vw;/);
  assert.doesNotMatch(basePanel, /overflow-y:/);
  assert.match(styles, /\.auth-panel-document-scroll \{[^}]*height: auto;[^}]*overflow-y: visible;/);
  assert.match(styles, /\.auth-panel-scrollable \{[^}]*overflow-y: auto;/);
});

test("mobile auth layout restores natural document height and scrolling", () => {
  const mobile = styles.match(/@media \(max-width: 800px\) \{ \.auth-page \{[^\n]+/)?.[0] ?? "";
  assert.match(mobile, /\.auth-page \{[^}]*height: auto;[^}]*min-height: 100dvh;[^}]*overflow: visible;/);
  assert.match(mobile, /\.auth-panel \{[^}]*height: auto;[^}]*min-height: calc\(100dvh - 230px\);[^}]*overflow-y: visible;/);
});

test("all public auth routes use the shared auth layout", () => {
  for (const route of ["login", "register", "forgot-password", "reset-password"]) {
    const page = readFileSync(new URL(`../src/app/(auth)/${route}/page.tsx`, import.meta.url), "utf8");
    assert.match(page, /className="auth-page(?:\s|\")/);
    assert.match(page, /className="auth-panel(?:\s|\")/);
  }
});

test("auth routes use only their intended overflow modifiers", () => {
  const login = readFileSync(new URL("../src/app/(auth)/login/page.tsx", import.meta.url), "utf8");
  assert.match(login, /className="auth-page"/);
  assert.match(login, /className="auth-panel"/);
  assert.doesNotMatch(login, /auth-page-document-scroll|auth-panel-document-scroll|auth-panel-scrollable/);

  for (const route of ["forgot-password", "reset-password"]) {
    const page = readFileSync(new URL(`../src/app/(auth)/${route}/page.tsx`, import.meta.url), "utf8");
    assert.match(page, /auth-page auth-page-document-scroll/);
    assert.match(page, /auth-panel auth-panel-document-scroll/);
  }

  const register = readFileSync(new URL("../src/app/(auth)/register/page.tsx", import.meta.url), "utf8");
  assert.doesNotMatch(register, /auth-page-document-scroll/);
  assert.match(register, /auth-panel auth-panel-scrollable/);
});
