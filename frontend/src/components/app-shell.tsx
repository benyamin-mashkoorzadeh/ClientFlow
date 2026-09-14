"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState, type ReactNode } from "react";
import { useAuth } from "@/context/auth-context";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

const navigation = [{ href: "/dashboard", label: "Dashboard", icon: "⌂" }, { href: "/clients", label: "Clients", icon: "◇" }, { href: "/projects", label: "Projects", icon: "◫" }, { href: "/tasks", label: "Tasks", icon: "✓" }, { href: "/invoices", label: "Invoices", icon: "▤" }];

export function AppShell({ children }: { children: ReactNode }) {
  const { user, workspace, logout } = useAuth(); const [open, setOpen] = useState(false); const pathname = usePathname(); const router = useRouter();
  const signOut = async () => { await logout(); router.replace("/login"); };
  return <div className="app-frame">{open && <button className="sidebar-backdrop" type="button" aria-label="Close navigation" onClick={() => setOpen(false)} />}<aside id="main-sidebar" className={`sidebar ${open ? "sidebar-open" : ""}`}><div className="brand-lockup"><BrandLogo tone="dark" /></div><div className="sidebar-scroll"><div className="workspace-chip"><span className="status-dot" />{workspace?.name ?? "Your workspace"}</div><nav className="main-nav" aria-label="Main navigation"><span className="nav-label">Workspace</span>{navigation.map((item) => { const active = pathname === item.href || (item.href !== "/dashboard" && pathname.startsWith(item.href)); return <Link key={item.href} href={item.href} className={`nav-link ${active ? "nav-link-active" : ""}`} onClick={() => setOpen(false)}><span className="nav-icon">{item.icon}</span>{item.label}</Link>; })}</nav></div><div className="sidebar-footer"><div className="profile-mini"><div className="avatar">{user?.name.charAt(0).toUpperCase()}</div><div><strong>{user?.name}</strong><span>{user?.email}</span></div></div><button className="sign-out" onClick={() => void signOut()}>↗ <span>Sign out</span></button></div></aside><div className="main-column"><header className="topbar"><button className="menu-button" onClick={() => setOpen(!open)} aria-label="Toggle navigation" aria-expanded={open} aria-controls="main-sidebar">☰</button><span className="topbar-brand"><BrandLogo variant="mark" /></span><span className="topbar-context">Operations overview</span><div className="topbar-right"><ThemeToggle /><span className="live-pill"><span className="status-dot" />Live workspace</span><div className="avatar avatar-small">{user?.name.charAt(0).toUpperCase()}</div></div></header><main className="content-area">{children}</main></div></div>;
}
