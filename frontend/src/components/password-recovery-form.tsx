"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";
import { api } from "@/lib/api";
import { ApiError, type ApiValidationErrors } from "@/lib/types";

function RecoveryField({ label, name, type, value, onChange, error, autoComplete }: { label: string; name: string; type: string; value: string; onChange: (value: string) => void; error?: string; autoComplete: string }) {
  return <label className="field"><span>{label}</span><input name={name} type={type} required value={value} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} aria-invalid={Boolean(error)} aria-describedby={error ? `${name}-error` : undefined} />{error && <small id={`${name}-error`} className="auth-field-error">{error}</small>}</label>;
}

export function ForgotPasswordForm() {
  const [email, setEmail] = useState("");
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy) return;
    setBusy(true); setErrors({}); setError(null);
    try {
      await api.forgotPassword(email);
      setSent(true);
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 400) setSent(true);
      else if (cause instanceof ApiError && cause.status === 422) setErrors(cause.errors);
      else setError(cause instanceof ApiError && cause.status === 403 ? "This request is not available right now." : "We could not process your request. Please try again.");
    } finally { setBusy(false); }
  };

  if (sent) return <div className="auth-form auth-result" role="status"><strong>Check your email</strong><p>If an account matches that address, you’ll receive a password reset link. Check your inbox and spam folder.</p><Link href="/login">Back to sign in</Link></div>;

  return <form className="auth-form" onSubmit={submit} noValidate><RecoveryField label="Email" name="email" type="email" value={email} onChange={setEmail} error={errors.email?.[0]} autoComplete="email" />{error && <div className="form-error" role="alert">{error}</div>}<button className="primary-button" type="submit" disabled={busy}>{busy ? "Sending..." : "Send reset link"}<span>→</span></button><p className="form-switch">Remembered your password? <Link href="/login">Sign in</Link></p></form>;
}

export function ResetPasswordForm() {
  const params = useSearchParams();
  const router = useRouter();
  const token = params.get("token") ?? "";
  const queryEmail = params.get("email") ?? "";
  const [emailState, setEmailState] = useState({ queryEmail, value: queryEmail });
  const email = emailState.queryEmail === queryEmail ? emailState.value : queryEmail;
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [errors, setErrors] = useState<ApiValidationErrors>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (busy || !token.trim()) return;
    setBusy(true); setErrors({}); setError(null);
    try {
      await api.resetPassword({ token, email, password, password_confirmation: confirmation });
      setPassword(""); setConfirmation("");
      router.replace("/login?reset=success");
    } catch (cause) {
      if (cause instanceof ApiError && cause.status === 422) setErrors(cause.errors);
      else if (cause instanceof ApiError && cause.status === 400) setError("This reset link is invalid or expired. Request a new one.");
      else setError("Your password could not be reset. Please try again.");
      setBusy(false);
    }
  };

  if (!token.trim()) return <div className="auth-form auth-result" role="alert"><strong>Reset link missing</strong><p>Open the complete link from your reset email, or request another one.</p><Link href="/forgot-password">Request a new link</Link></div>;

  return <form className="auth-form" onSubmit={submit} noValidate>{!queryEmail && <p className="auth-form-hint">Enter the email address that received the reset link.</p>}<RecoveryField label="Email" name="email" type="email" value={email} onChange={(value) => setEmailState({ queryEmail, value })} error={errors.email?.[0]} autoComplete="email" /><RecoveryField label="New password" name="password" type="password" value={password} onChange={setPassword} error={errors.password?.[0]} autoComplete="new-password" /><RecoveryField label="Confirm new password" name="password_confirmation" type="password" value={confirmation} onChange={setConfirmation} error={errors.password_confirmation?.[0]} autoComplete="new-password" />{errors.token && <div className="form-error" role="alert">This reset link is invalid or expired. Request a new one.</div>}{error && <div className="form-error" role="alert">{error} {error.includes("invalid or expired") && <Link href="/forgot-password">Request a new link</Link>}</div>}<button className="primary-button" type="submit" disabled={busy}>{busy ? "Resetting..." : "Reset password"}<span>→</span></button><p className="form-switch"><Link href="/login">Back to sign in</Link></p></form>;
}
