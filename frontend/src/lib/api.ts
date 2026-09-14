import { ApiError, type AuthResponse, type ClientListResponse, type ClientPayload, type ClientResponse, type DashboardData, type InvoiceListResponse, type InvoicePayload, type InvoiceResponse, type InvoiceStatus, type ProjectListResponse, type ProjectPayload, type ProjectResponse, type TaskListResponse, type TaskPayload, type TaskResponse } from "@/lib/types";

const API_URL = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "");

if (!API_URL) throw new Error("NEXT_PUBLIC_API_URL must be configured.");

function xsrfToken(): string | undefined {
  if (typeof document === "undefined") return undefined;
  const cookie = document.cookie.split("; ").find((value) => value.startsWith("XSRF-TOKEN="));
  return cookie ? decodeURIComponent(cookie.split("=").slice(1).join("=")) : undefined;
}

async function parseResponse<T>(response: Response): Promise<T> {
  const payload = (response.headers.get("content-type") ?? "").includes("application/json") ? await response.json() : null;
  if (response.status === 401 && typeof window !== "undefined") window.dispatchEvent(new Event("clientflow:session-expired"));
  if (!response.ok) {
    const message = response.status >= 500 ? "The server could not complete this request. Please try again." : response.status === 419 ? "Your session expired. Please try again." : payload?.message ?? "Something went wrong while contacting the server.";
    throw new ApiError(message, response.status, response.status >= 500 ? {} : payload?.errors ?? {});
  }
  return payload as T;
}

type CsrfRequirement = "bootstrap" | "ensure";

async function request<T>(path: string, init: RequestInit = {}, csrf?: CsrfRequirement): Promise<T> {
  if (csrf === "bootstrap" || (csrf === "ensure" && !xsrfToken())) {
    await parseResponse<void>(await fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: "include", headers: { Accept: "application/json" } }));
  }
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  if (init.body) headers.set("Content-Type", "application/json");
  const token = xsrfToken();
  if (csrf && !token) throw new ApiError("A secure session could not be prepared. Refresh the page and try again.", 419);
  if (token) headers.set("X-XSRF-TOKEN", token);
  return parseResponse<T>(await fetch(`${API_URL}${path}`, { ...init, headers, credentials: "include" }));
}

export const api = {
  currentUser: () => request<AuthResponse>("/api/user"),
  login: (payload: { email: string; password: string; remember?: boolean }) => request<AuthResponse>("/api/login", { method: "POST", body: JSON.stringify(payload) }, "bootstrap"),
  register: (payload: { name: string; email: string; password: string; password_confirmation: string }) => request<AuthResponse>("/api/register", { method: "POST", body: JSON.stringify(payload) }, "bootstrap"),
  forgotPassword: (email: string) => request<{ message: string }>("/api/forgot-password", { method: "POST", body: JSON.stringify({ email }) }, "bootstrap"),
  resetPassword: (payload: { token: string; email: string; password: string; password_confirmation: string }) => request<{ message: string }>("/api/reset-password", { method: "POST", body: JSON.stringify(payload) }, "bootstrap"),
  resendVerification: () => request<{ message: string }>("/api/email/verification-notification", { method: "POST" }, "ensure"),
  logout: () => request<{ message: string }>("/api/logout", { method: "POST" }, "ensure"),
  dashboard: () => request<{ data: DashboardData }>("/api/dashboard"),
  clients: (params: { page?: number; search?: string; per_page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.page) query.set("page", String(params.page));
    if (params.search) query.set("search", params.search);
    if (params.per_page) query.set("per_page", String(params.per_page));
    return request<ClientListResponse>(`/api/clients${query.toString() ? `?${query}` : ""}`);
  },
  client: (id: string) => request<ClientResponse>(`/api/clients/${id}`),
  createClient: (payload: ClientPayload) => request<ClientResponse>("/api/clients", { method: "POST", body: JSON.stringify(payload) }, "ensure"),
  updateClient: (id: string, payload: ClientPayload) => request<ClientResponse>(`/api/clients/${id}`, { method: "PATCH", body: JSON.stringify(payload) }, "ensure"),
  deleteClient: (id: string) => request<{ message: string }>(`/api/clients/${id}`, { method: "DELETE" }, "ensure"),
  projects: (params: { page?: number; search?: string; status?: string; client_id?: number; per_page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.page) query.set("page", String(params.page));
    if (params.search) query.set("search", params.search);
    if (params.status) query.set("status", params.status);
    if (params.client_id) query.set("client_id", String(params.client_id));
    if (params.per_page) query.set("per_page", String(params.per_page));
    return request<ProjectListResponse>(`/api/projects${query.toString() ? `?${query}` : ""}`);
  },
  project: (id: string) => request<ProjectResponse>(`/api/projects/${id}`),
  createProject: (payload: ProjectPayload) => request<ProjectResponse>("/api/projects", { method: "POST", body: JSON.stringify(payload) }, "ensure"),
  updateProject: (id: string, payload: Partial<ProjectPayload>) => request<ProjectResponse>(`/api/projects/${id}`, { method: "PATCH", body: JSON.stringify(payload) }, "ensure"),
  deleteProject: (id: string) => request<{ message: string }>(`/api/projects/${id}`, { method: "DELETE" }, "ensure"),
  tasks: (params: { page?: number; search?: string; status?: string; priority?: string; project_id?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.page) query.set("page", String(params.page));
    if (params.search) query.set("search", params.search);
    if (params.status) query.set("status", params.status);
    if (params.priority) query.set("priority", params.priority);
    if (params.project_id) query.set("project_id", String(params.project_id));
    return request<TaskListResponse>(`/api/tasks${query.toString() ? `?${query}` : ""}`);
  },
  task: (id: string) => request<TaskResponse>(`/api/tasks/${id}`),
  createTask: (payload: TaskPayload) => request<TaskResponse>("/api/tasks", { method: "POST", body: JSON.stringify(payload) }, "ensure"),
  updateTask: (id: string, payload: Partial<TaskPayload>) => request<TaskResponse>(`/api/tasks/${id}`, { method: "PATCH", body: JSON.stringify(payload) }, "ensure"),
  deleteTask: (id: string) => request<{ message: string }>(`/api/tasks/${id}`, { method: "DELETE" }, "ensure"),
  invoices: (params: { page?: number; search?: string; status?: InvoiceStatus; client_id?: number; per_page?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.page) query.set("page", String(params.page));
    if (params.search) query.set("search", params.search);
    if (params.status) query.set("status", params.status);
    if (params.client_id) query.set("client_id", String(params.client_id));
    if (params.per_page) query.set("per_page", String(params.per_page));
    return request<InvoiceListResponse>(`/api/invoices${query.toString() ? `?${query}` : ""}`);
  },
  invoice: (id: string) => request<InvoiceResponse>(`/api/invoices/${id}`),
  createInvoice: (payload: InvoicePayload) => request<InvoiceResponse>("/api/invoices", { method: "POST", body: JSON.stringify(payload) }, "ensure"),
  updateInvoice: (id: string, payload: Partial<InvoicePayload>) => request<InvoiceResponse>(`/api/invoices/${id}`, { method: "PATCH", body: JSON.stringify(payload) }, "ensure"),
  updateInvoiceStatus: (id: string, status: InvoiceStatus) => request<InvoiceResponse>(`/api/invoices/${id}`, { method: "PATCH", body: JSON.stringify({ status }) }, "ensure"),
  deleteInvoice: (id: string) => request<{ message: string }>(`/api/invoices/${id}`, { method: "DELETE" }, "ensure"),
  downloadInvoicePdf: async (id: string): Promise<{ blob: Blob; filename: string | null }> => {
    const response = await fetch(`${API_URL}/api/invoices/${id}/pdf`, { credentials: "include", headers: { Accept: "application/pdf, application/json" } });
    if (!response.ok) await parseResponse<never>(response);
    if (!(response.headers.get("content-type") ?? "").toLowerCase().includes("application/pdf")) {
      throw new ApiError("The server did not return an invoice PDF.", response.status);
    }
    const disposition = response.headers.get("content-disposition") ?? "";
    const filename = disposition.match(/filename\s*=\s*"?([^";]+)"?/i)?.[1] ?? null;
    return { blob: await response.blob(), filename };
  },
};
