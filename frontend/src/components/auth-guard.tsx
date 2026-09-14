"use client";

import { useEffect, type ReactNode } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { EmailVerificationNotice } from "@/components/email-verification-notice";

export function AuthGuard({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth(); const router = useRouter(); const pathname = usePathname();
  useEffect(() => { if (!isLoading && !user) router.replace(`/login?next=${encodeURIComponent(pathname)}`); }, [isLoading, pathname, router, user]);
  if (isLoading || !user) return <div className="screen-state"><span className="spinner" />Loading your workspace...</div>;
  if (!user.email_verified_at) return <div className="verification-screen"><EmailVerificationNotice /></div>;
  return <>{children}</>;
}
