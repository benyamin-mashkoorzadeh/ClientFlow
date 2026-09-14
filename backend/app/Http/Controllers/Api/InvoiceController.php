<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private InvoicePdfService $invoicePdfService,
    )
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $query = Invoice::query()
            ->with(['client:id,name,company', 'items'])
            ->where('workspace_id', $user->activeWorkspace()->id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $invoices = $query->latest('id')->paginate($perPage);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $invoice = $this->invoiceService->create(Auth::user(), $request->validated());

        return response()->json(['data' => new InvoiceResource($invoice)], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->ensureActiveWorkspace($invoice);
        $this->authorize('view', $invoice);

        return response()->json(['data' => new InvoiceResource(
            $invoice->load(['client', 'items'])
        )]);
    }

    public function pdf(Invoice $invoice): mixed
    {
        $this->ensureActiveWorkspace($invoice);
        $this->authorize('view', $invoice);

        return $this->invoicePdfService->download($invoice);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->ensureActiveWorkspace($invoice);
        $this->authorize('update', $invoice);
        $updated = $this->invoiceService->update(Auth::user(), $invoice, $request->validated());

        return response()->json(['data' => new InvoiceResource($updated)]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->ensureActiveWorkspace($invoice);
        $this->authorize('delete', $invoice);
        $this->invoiceService->delete($invoice);

        return response()->json(['message' => 'Invoice deleted successfully.']);
    }

    private function ensureActiveWorkspace(Invoice $invoice): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless(
            $invoice->workspace_id === $user->activeWorkspace()?->id,
            403,
        );
    }
}
