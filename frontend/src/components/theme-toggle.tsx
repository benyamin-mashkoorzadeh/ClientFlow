"use client";

import { useTheme } from "@/context/theme-context";
import type { ThemePreference } from "@/lib/theme";

export function ThemeToggle() {
  const { preference, setPreference } = useTheme();

  return <label className="theme-control">
    <span className="sr-only">Color theme</span>
    <select aria-label="Color theme" value={preference} onChange={(event) => setPreference(event.target.value as ThemePreference)}>
      <option value="system">System</option>
      <option value="light">Light</option>
      <option value="dark">Dark</option>
    </select>
  </label>;
}
