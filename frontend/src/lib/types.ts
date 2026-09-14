export type Workspace = { id: number; name: string; slug: string; default_currency: string };
export type User = { id: number; name: string; email: string; email_verified_at: string | null };
export type AuthResponse = { message?: string; user: User; workspace: Workspace | null };
export type Client = { id: number; workspace_id: number; name: string; company: string | null; email: string | null; phone: string | null; address: string | null; notes: string | null; created_at: string | null; updated_at: string | null };
export type ClientPayload = { name: string; company?: string; email?: string; phone?: string; address?: string; notes?: string };
export type ClientResponse = { data: Client };
export type ClientListResponse = { data: Client[]; meta: { current_page: number; per_page: number; total: number; last_page: number } };
export type ProjectStatus = "active" | "completed" | "on_hold";
export type Project = { id: number; workspace_id: number; client_id: number; name: string; description: string | null; status: ProjectStatus; start_date: string | null; end_date: string | null; budget_cents: number | null; currency_code: string | null; client: { id: number; name: string; company: string | null }; created_at: string | null; updated_at: string | null };
export type ProjectPayload = { client_id: number; name: string; description?: string; status: ProjectStatus; start_date?: string; end_date?: string; budget_cents?: number | null; currency_code?: string };
export type ProjectResponse = { data: Project };
export type ProjectListResponse = { data: Project[]; meta: { current_page: number; per_page: number; total: number; last_page: number } };
export type TaskStatus = "todo" | "in_progress" | "done";
export type TaskPriority = "low" | "medium" | "high";
export type Task = { id: number; workspace_id: number; project_id: number; title: string; description: string | null; deadline: string | null; priority: TaskPriority; status: TaskStatus; project: { id: number; name: string }; created_at: string | null; updated_at: string | null };
export type TaskPayload = { project_id: number; title: string; description?: string; deadline?: string; priority: TaskPriority; status: TaskStatus };
export type TaskResponse = { data: Task };
export type TaskListResponse = { data: Task[]; meta: { current_page: number; per_page: number; total: number; last_page: number } };
export type InvoiceStatus = "draft" | "sent" | "paid" | "cancelled";
export type InvoiceItem = {
  id: number;
  description: string;
  quantity: number;
  unit_price_cents: number;
  discount_rate: string;
  tax_rate: string;
  line_subtotal_cents: number;
  line_discount_cents: number;
  line_tax_cents: number;
  line_total_cents: number;
};
export type Invoice = {
  id: number;
  workspace_id: number;
  client_id: number;
  invoice_number: string;
  issue_date: string;
  due_date: string;
  status: InvoiceStatus;
  currency_code: string;
  subtotal_cents: number;
  discount_cents: number;
  tax_cents: number;
  total_cents: number;
  is_overdue: boolean;
  notes: string | null;
  client: { id: number; name: string; company: string | null };
  items: InvoiceItem[];
  created_at: string | null;
  updated_at: string | null;
};
export type InvoiceResponse = { data: Invoice };
export type InvoiceListResponse = { data: Invoice[]; meta: { current_page: number; per_page: number; total: number; last_page: number } };
export type InvoiceItemPayload = { description: string; quantity: number; unit_price_cents: number; discount_rate: string; tax_rate: string };
export type InvoicePayload = { client_id: number; issue_date: string; due_date: string; currency_code?: string; notes: string; items: InvoiceItemPayload[] };
export type ApiValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  status: number;
  errors: ApiValidationErrors;
  constructor(message: string, status: number, errors: ApiValidationErrors = {}) { super(message); this.name = "ApiError"; this.status = status; this.errors = errors; }
}

export type DashboardData = {
  summary: {
    clients: { total: number };
    projects: { total: number; active: number; completed: number; on_hold: number };
    tasks: { total: number; todo: number; in_progress: number; done: number; overdue: number };
    invoices: { total: number; draft: number; sent: number; paid: number; cancelled: number; overdue: number };
    financial: { currency_code: string; paid_revenue_cents: number; outstanding_cents: number };
  };
  recent_clients: { id: number; name: string; company: string | null; created_at: string }[];
  recent_projects: { id: number; name: string; status: string; created_at: string; client: { id: number; name: string; company: string | null } }[];
  upcoming_tasks: { id: number; title: string; deadline: string; priority: string; status: string; project: { id: number; name: string } }[];
  recent_invoices: { id: number; invoice_number: string; status: string; is_overdue: boolean; total_cents: number; currency_code: string; issue_date: string; due_date: string; client: { id: number; name: string; company: string | null } }[];
};
