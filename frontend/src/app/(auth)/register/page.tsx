import { RegisterForm } from "@/components/auth-form";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export default function RegisterPage() { return <main className="auth-page"><div className="auth-atmosphere register-atmosphere"><div className="auth-orbit orbit-three" /><span className="auth-caption">ClientFlow / 02</span><div className="auth-quote"><span>+</span><p>Start with<br /><em>clarity.</em></p><small>Your workspace, ready for the work ahead.</small></div></div><section className="auth-panel auth-panel-scrollable"><div className="auth-brand"><BrandLogo /><ThemeToggle /></div><div className="auth-copy"><p className="eyebrow">New workspace</p><h1>Put your work<br /><em>in motion.</em></h1><p>Create your workspace in under a minute.</p></div><RegisterForm /></section></main>; }
