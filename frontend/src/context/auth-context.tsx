"use client";

import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "@/lib/api";
import { ApiError, type User, type Workspace } from "@/lib/types";

type AuthContextValue = { user: User | null; workspace: Workspace | null; isLoading: boolean; login: (email: string, password: string, remember: boolean) => Promise<void>; register: (name: string, email: string, password: string, confirmation: string) => Promise<void>; logout: () => Promise<void>; refreshUser: () => Promise<void> };
const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null); const [workspace, setWorkspace] = useState<Workspace | null>(null); const [isLoading, setIsLoading] = useState(true);
  useEffect(() => {
    const expire = () => { setUser(null); setWorkspace(null); };
    window.addEventListener("clientflow:session-expired", expire);
    return () => window.removeEventListener("clientflow:session-expired", expire);
  }, []);
  const refreshUser = async () => { try { const response = await api.currentUser(); setUser(response.user); setWorkspace(response.workspace); } catch (error) { if (error instanceof ApiError && error.status === 401) { setUser(null); setWorkspace(null); } else throw error; } finally { setIsLoading(false); } };
  useEffect(() => {
    let active = true;
    void api.currentUser().then((response) => {
      if (!active) return;
      setUser(response.user);
      setWorkspace(response.workspace);
    }).catch(() => {
      if (active) {
        setUser(null);
        setWorkspace(null);
      }
    }).finally(() => {
      if (active) setIsLoading(false);
    });
    return () => { active = false; };
  }, []);
  const login = async (email: string, password: string, remember: boolean) => { const response = await api.login({ email, password, remember }); setUser(response.user); setWorkspace(response.workspace); };
  const register = async (name: string, email: string, password: string, confirmation: string) => { const response = await api.register({ name, email, password, password_confirmation: confirmation }); setUser(response.user); setWorkspace(response.workspace); };
  const logout = async () => { await api.logout(); setUser(null); setWorkspace(null); };
  return <AuthContext.Provider value={{ user, workspace, isLoading, login, register, logout, refreshUser }}>{children}</AuthContext.Provider>;
}

export function useAuth() { const context = useContext(AuthContext); if (!context) throw new Error("useAuth must be used inside AuthProvider"); return context; }
