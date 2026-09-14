<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function summary(User $user): array
    {
        $workspace = $user->activeWorkspace();
        abort_unless($workspace !== null, 403);

        $today = Carbon::today()->toDateString();

        $clientMetrics = Client::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('COUNT(*) AS total')
            ->first();

        $projectMetrics = Project::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) AS on_hold")
            ->first();

        $taskMetrics = Task::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) AS todo,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN deadline < ? AND status != 'done' THEN 1 ELSE 0 END) AS overdue", [$today])
            ->first();

        $invoiceMetrics = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN due_date < ? AND status NOT IN ('paid', 'cancelled') THEN 1 ELSE 0 END) AS overdue", [$today])
            ->first();

        $financials = Invoice::query()
            ->where('workspace_id', $workspace->id)
            ->where('currency_code', $workspace->default_currency)
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN total_cents ELSE 0 END), 0) AS paid_revenue_cents,
                COALESCE(SUM(CASE WHEN status = 'sent' THEN total_cents ELSE 0 END), 0) AS outstanding_cents")
            ->first();

        return [
            'summary' => [
                'clients' => [
                    'total' => (int) $clientMetrics->total,
                ],
                'projects' => [
                    'total' => (int) $projectMetrics->total,
                    'active' => (int) $projectMetrics->active,
                    'completed' => (int) $projectMetrics->completed,
                    'on_hold' => (int) $projectMetrics->on_hold,
                ],
                'tasks' => [
                    'total' => (int) $taskMetrics->total,
                    'todo' => (int) $taskMetrics->todo,
                    'in_progress' => (int) $taskMetrics->in_progress,
                    'done' => (int) $taskMetrics->done,
                    'overdue' => (int) $taskMetrics->overdue,
                ],
                'invoices' => [
                    'total' => (int) $invoiceMetrics->total,
                    'draft' => (int) $invoiceMetrics->draft,
                    'sent' => (int) $invoiceMetrics->sent,
                    'paid' => (int) $invoiceMetrics->paid,
                    'cancelled' => (int) $invoiceMetrics->cancelled,
                    'overdue' => (int) $invoiceMetrics->overdue,
                ],
                'financial' => [
                    'currency_code' => $workspace->default_currency,
                    'paid_revenue_cents' => (int) $financials->paid_revenue_cents,
                    'outstanding_cents' => (int) $financials->outstanding_cents,
                ],
            ],
            'recent_clients' => Client::query()
                ->where('workspace_id', $workspace->id)
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'name', 'company', 'created_at']),
            'recent_projects' => Project::query()
                ->where('workspace_id', $workspace->id)
                ->with('client:id,name,company')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'client_id', 'name', 'status', 'created_at']),
            'upcoming_tasks' => Task::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', '!=', 'done')
                ->whereNotNull('deadline')
                ->whereDate('deadline', '>=', $today)
                ->with('project:id,name')
                ->orderBy('deadline')
                ->limit(5)
                ->get(['id', 'project_id', 'title', 'deadline', 'priority', 'status']),
            'recent_invoices' => Invoice::query()
                ->where('workspace_id', $workspace->id)
                ->with('client:id,name,company')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'client_id', 'invoice_number', 'status', 'total_cents', 'currency_code', 'issue_date', 'due_date']),
        ];
    }
}