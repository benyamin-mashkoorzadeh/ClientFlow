import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import ts from "typescript";

const source = readFileSync(new URL("../src/lib/theme.ts", import.meta.url), "utf8");
const javascript = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;

function themeHarness(initial = null, systemDark = false) {
  let stored = initial;
  let dark = systemDark;
  const localStorage = {
    getItem: () => stored,
    setItem: (_key, value) => { stored = value; },
    removeItem: () => { stored = null; },
  };
  const matchMedia = () => ({ matches: dark });
  const document = { documentElement: { dataset: {} } };
  const window = { localStorage, matchMedia };
  const compiledModule = { exports: {} };
  new Function("module", "exports", "window", "document", javascript)(compiledModule, compiledModule.exports, window, document);
  return {
    theme: compiledModule.exports,
    document,
    localStorage,
    matchMedia,
    setSystemDark: (value) => { dark = value; },
    getStored: () => stored,
  };
}

test("system preference follows the operating system and explicit choices override it", () => {
  const { theme, document, setSystemDark, getStored } = themeHarness(null, true);
  assert.equal(theme.readThemePreference(), "system");
  theme.applyTheme("system");
  assert.equal(document.documentElement.dataset.theme, "dark");

  theme.saveThemePreference("light");
  theme.applyTheme(theme.readThemePreference());
  assert.equal(getStored(), "light");
  assert.equal(document.documentElement.dataset.theme, "light");

  theme.saveThemePreference("system");
  setSystemDark(false);
  theme.applyTheme(theme.readThemePreference());
  assert.equal(getStored(), null);
  assert.equal(document.documentElement.dataset.theme, "light");
});

test("the inline bootstrap applies the saved or system theme before hydration", () => {
  for (const [stored, systemDark, expected] of [
    [null, true, "dark"], [null, false, "light"], ["light", true, "light"], ["dark", false, "dark"],
  ]) {
    const { theme, document, localStorage, matchMedia } = themeHarness(stored, systemDark);
    new Function("localStorage", "matchMedia", "document", theme.themeInitializationScript)(localStorage, matchMedia, document);
    assert.equal(document.documentElement.dataset.theme, expected);
  }
});
