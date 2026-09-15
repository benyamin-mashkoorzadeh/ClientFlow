import { ForgotPasswordForm } from "@/components/password-recovery-form";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export default function ForgotPasswordPage() {
  return <main className="auth-page auth-page-document-scroll"><div className="auth-atmosphere"><div className="auth-orbit orbit-one" /><div className="auth-orbit orbit-two" /><span className="auth-caption">ClientFlow / 03</span><div className="auth-quote"><span>“</span><p>Back to<br />your flow.</p><small>A fresh start is just an email away.</small></div></div><section className="auth-panel auth-panel-document-scroll"><div className="auth-brand"><BrandLogo /><ThemeToggle /></div><div className="auth-copy"><p className="eyebrow">Account recovery</p><h1>Forgot your<br /><em>password?</em></h1><p>Enter your email and we’ll send you a reset link if an account exists.</p></div><ForgotPasswordForm /></section></main>;
}
