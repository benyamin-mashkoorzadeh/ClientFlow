import { Suspense } from "react";
import { ResetPasswordForm } from "@/components/password-recovery-form";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export default function ResetPasswordPage() {
  return <main className="auth-page"><div className="auth-atmosphere"><div className="auth-orbit orbit-one" /><div className="auth-orbit orbit-two" /><span className="auth-caption">ClientFlow / 04</span><div className="auth-quote"><span>“</span><p>A new<br />beginning.</p><small>Set a new password and get back to your work.</small></div></div><section className="auth-panel"><div className="auth-brand"><BrandLogo /><ThemeToggle /></div><div className="auth-copy"><p className="eyebrow">Account recovery</p><h1>Reset your<br /><em>password.</em></h1><p>Choose a new password for your account.</p></div><Suspense fallback={<div className="screen-state"><span className="spinner" />Loading reset link...</div>}><ResetPasswordForm /></Suspense></section></main>;
}
