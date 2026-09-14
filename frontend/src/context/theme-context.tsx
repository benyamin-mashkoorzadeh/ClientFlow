"use client";

import { createContext, useContext, useEffect, useLayoutEffect, useSyncExternalStore, type ReactNode } from "react";
import { applyTheme, readThemePreference, saveThemePreference, type ThemePreference } from "@/lib/theme";

type ThemeContextValue = {
  preference: ThemePreference;
  setPreference: (preference: ThemePreference) => void;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);
const THEME_CHANGE_EVENT = "clientflow-theme-change";

function subscribeToPreference(onChange: () => void) {
  const sync = () => { applyTheme(readThemePreference()); onChange(); };
  window.addEventListener(THEME_CHANGE_EVENT, sync);
  window.addEventListener("storage", sync);
  return () => {
    window.removeEventListener(THEME_CHANGE_EVENT, sync);
    window.removeEventListener("storage", sync);
  };
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const preference = useSyncExternalStore<ThemePreference>(subscribeToPreference, readThemePreference, () => "system");

  useLayoutEffect(() => {
    applyTheme(readThemePreference());
  }, []);

  useEffect(() => {
    const media = window.matchMedia("(prefers-color-scheme: dark)");
    const onSystemChange = () => {
      if (preference === "system") applyTheme("system");
    };
    media.addEventListener("change", onSystemChange);
    return () => media.removeEventListener("change", onSystemChange);
  }, [preference]);

  const setPreference = (next: ThemePreference) => {
    saveThemePreference(next);
    applyTheme(next);
    window.dispatchEvent(new Event(THEME_CHANGE_EVENT));
  };

  return <ThemeContext.Provider value={{ preference, setPreference }}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) throw new Error("useTheme must be used within ThemeProvider");
  return context;
}
