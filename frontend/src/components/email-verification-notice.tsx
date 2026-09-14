"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { api } from "@/lib/api";
import { ApiError } from "@/lib/types";
import { BrandLogo } from "@/components/brand-logo";
import { ThemeToggle } from "@/components/theme-toggle";

export function EmailVerificationNotice() {
  const { user, refreshUser, logout } = useAuth();
  const router = useRouter();
  const pending = useRef(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  if (!user || user.email_verified_at) return null;

  const run = async (action: "resend" | "refresh" | "logout") => {
    if (pending.current) return;
    pending.current = true; setBusy(true); setMessage(null); setError(null);
    try {
      if (action === "logout") {
        await logout();
        router.replace("/login");
      } else if (action === "resend") {
        await api.resendVerification();
        setMessage("Verification link sent. Check your inbox and spam folder.");
      } else {
        await refreshUser();
        setMessage("If you opened the verification link, this notice will disappear once your account is updated.");
      }
    } catch (cause) {
      setError(cause instanceof ApiError && cause.status === 429 ? "Please wait before requesting another link." : "This action could not be completed. Please try again.");
    } finally { pending.current = false; setBusy(false); }
  };

  return <main className="verification-notice" aria-label="Email verification"><div><div className="auth-brand"><BrandLogo /><ThemeToggle /></div><strong>Verify your email</strong><p>We sent a link to <b>{user.email}</b>. Open it in this browser, then return here to refresh your account.</p>{message && <p role="status" className="verification-message">{message}</p>}{error && <p role="alert" className="verification-error">{error}</p>}</div><div className="verification-actions"><button type="button" onClick={() => void run("resend")} disabled={busy}>Resend link</button><button type="button" onClick={() => void run("refresh")} disabled={busy}>I’ve verified</button><button type="button" onClick={() => void run("logout")} disabled={busy}>Sign out</button></div></main>;
}
