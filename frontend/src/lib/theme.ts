export type ThemePreference = "light" | "dark" | "system";

export const THEME_STORAGE_KEY = "clientflow-theme";
let memoryPreference: ThemePreference = "system";

export function readThemePreference(): ThemePreference {
  try {
    const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    return stored === "light" || stored === "dark" ? stored : "system";
  } catch {
    return memoryPreference;
  }
}

export function saveThemePreference(preference: ThemePreference): void {
  memoryPreference = preference;
  try {
    if (preference === "system") window.localStorage.removeItem(THEME_STORAGE_KEY);
    else window.localStorage.setItem(THEME_STORAGE_KEY, preference);
  } catch { /* Keep the preference for this visit when storage is unavailable. */ }
}

export function applyTheme(preference: ThemePreference): void {
  const systemDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  document.documentElement.dataset.theme = preference === "system"
    ? systemDark ? "dark" : "light"
    : preference;
}

export const themeInitializationScript = `try{var preference=localStorage.getItem("${THEME_STORAGE_KEY}");var dark=preference==="dark"||(preference!=="light"&&matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.dataset.theme=dark?"dark":"light"}catch(error){document.documentElement.dataset.theme=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"}`;
