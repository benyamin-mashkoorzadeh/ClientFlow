import { Suspense } from "react";
import { LoginForm } from "@/components/auth-form";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export default function LoginPage() { return <main className="auth-page"><div className="auth-atmosphere"><div className="auth-orbit orbit-one" /><div className="auth-orbit orbit-two" /><span className="auth-caption">ClientFlow / 01</span><div className="auth-quote"><span>“</span><p>Less chasing.<br />More flowing.</p><small>A calmer operating rhythm for growing businesses.</small></div></div><section className="auth-panel"><div className="auth-brand"><BrandLogo /><ThemeToggle /></div><div className="auth-copy"><p className="eyebrow">Welcome back</p><h1>Make room for<br /><em>good work.</em></h1><p>Sign in to see what is moving across your workspace.</p></div><Suspense fallback={<div className="screen-state"><span className="spinner" />Loading...</div>}><LoginForm /></Suspense></section></main>; }
