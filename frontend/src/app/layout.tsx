import type { Metadata } from "next";
import { AuthProvider } from "@/context/auth-context";
import { ThemeProvider } from "@/context/theme-context";
import { themeInitializationScript } from "@/lib/theme";
import "./globals.css";

export const metadata: Metadata = {
  title: "ClientFlow",
  description: "Client and work management for freelancers and small service businesses.",
  icons: { icon: [{ url: "/brand/clientflow-app-icon.svg", type: "image/svg+xml", sizes: "any" }] },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en" suppressHydrationWarning><head><script dangerouslySetInnerHTML={{ __html: themeInitializationScript }} /></head><body><ThemeProvider><AuthProvider>{children}</AuthProvider></ThemeProvider></body></html>;
}
