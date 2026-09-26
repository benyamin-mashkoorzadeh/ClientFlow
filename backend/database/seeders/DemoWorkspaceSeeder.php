<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoWorkspaceSeeder extends Seeder
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            [$user, $workspace] = $this->demoIdentity();

            $this->resetWorkspace($workspace);
            $clients = $this->seedClients($workspace);
            $projects = $this->seedProjects($workspace, $clients);
            $this->seedTasks($workspace, $projects);
            $this->seedInvoices($user, $clients);
        });
    }

    /** @return array{User, Workspace} */
    private function demoIdentity(): array
    {
        $email = (string) config('demo.email');
        $slug = (string) config('demo.workspace_slug');
        $user = User::query()->where('email', $email)->first();
        $workspace = Workspace::query()->where('slug', $slug)->first();

        if (($user && ! $workspace) || (! $user && $workspace)) {
            throw new RuntimeException('The reserved demo identifiers are already in use.');
        }

        if ($user && $workspace) {
            $hasDemoMembership = $user->memberships()->where('workspace_id', $workspace->id)->exists();
            $hasOtherMemberships = $user->memberships()->where('workspace_id', '!=', $workspace->id)->exists();
            $hasOtherMembers = $workspace->memberships()->where('user_id', '!=', $user->id)->exists();

            if (! $hasDemoMembership || $hasOtherMemberships || $hasOtherMembers) {
                throw new RuntimeException('The reserved demo identifiers belong to existing application data.');
            }
        } else {
            $user = User::create([
                'name' => 'ClientFlow Demo',
                'email' => $email,
                'password' => Str::password(48),
                'email_verified_at' => now(),
            ]);

            $workspace = Workspace::create([
                'name' => 'Northstar Creative Studio',
                'slug' => $slug,
                'default_currency' => 'EUR',
            ]);

            WorkspaceMembership::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role' => 'owner',
            ]);
        }

        $user->forceFill(['name' => 'ClientFlow Demo', 'email_verified_at' => now()])->save();
        $workspace->update([
            'name' => 'Northstar Creative Studio',
            'default_currency' => 'EUR',
        ]);
        $user->memberships()->where('workspace_id', $workspace->id)->update(['role' => 'owner']);

        return [$user, $workspace];
    }

    private function resetWorkspace(Workspace $workspace): void
    {
        Invoice::withTrashed()->where('workspace_id', $workspace->id)->forceDelete();
        Task::withTrashed()->where('workspace_id', $workspace->id)->forceDelete();
        Project::withTrashed()->where('workspace_id', $workspace->id)->forceDelete();
        Client::withTrashed()->where('workspace_id', $workspace->id)->forceDelete();
    }

    /** @return array<string, Client> */
    private function seedClients(Workspace $workspace): array
    {
        $records = [
            'atlas' => ['name' => 'Maya Chen', 'company' => 'Atlas & Row', 'email' => 'maya@atlasandrow.example', 'phone' => '+49 30 555 0184', 'address' => "Köpenicker Straße 126\n10179 Berlin, Germany", 'notes' => 'Brand and digital partner for the 2026 product expansion.'],
            'harbor' => ['name' => 'Jonas Richter', 'company' => 'Harbor Health', 'email' => 'jonas@harborhealth.example', 'phone' => '+49 40 555 0137', 'address' => "Am Sandtorkai 32\n20457 Hamburg, Germany", 'notes' => 'Quarterly product design and conversion optimization retainer.'],
            'field' => ['name' => 'Sofia Marin', 'company' => 'Field Notes Travel', 'email' => 'sofia@fieldnotes.example', 'phone' => '+34 91 555 0149', 'address' => "Calle de Atocha 84\n28012 Madrid, Spain", 'notes' => 'Editorial travel platform with a seasonal campaign calendar.'],
            'lumen' => ['name' => 'Elias Novak', 'company' => 'Lumen Architecture', 'email' => 'elias@lumenarchitecture.example', 'phone' => '+43 1 555 0162', 'address' => "Neubaugasse 41\n1070 Vienna, Austria", 'notes' => 'Portfolio and lead-generation work for a growing architecture practice.'],
            'northwind' => ['name' => 'Amelia Brooks', 'company' => 'Northwind Coffee Roasters', 'email' => 'amelia@northwindcoffee.example', 'phone' => '+44 20 7946 0188', 'address' => "18 Rivington Street\nLondon EC2A 3DU, United Kingdom", 'notes' => 'E-commerce and wholesale launch support.'],
            'verdant' => ['name' => 'Nora Dubois', 'company' => 'Verdant Labs', 'email' => 'nora@verdantlabs.example', 'phone' => '+33 1 84 80 19 22', 'address' => "24 Rue du Sentier\n75002 Paris, France", 'notes' => 'Sustainability analytics startup preparing for a funding round.'],
        ];

        $clients = [];
        foreach ($records as $key => $record) {
            $clients[$key] = Client::create(['workspace_id' => $workspace->id, ...$record]);
        }

        return $clients;
    }

    /**
     * @param  array<string, Client>  $clients
     * @return array<string, Project>
     */
    private function seedProjects(Workspace $workspace, array $clients): array
    {
        $today = CarbonImmutable::today();
        $records = [
            'atlas-launch' => ['client' => 'atlas', 'name' => 'Atlas Product Launch', 'description' => 'Positioning, launch site, and campaign toolkit for a new B2B platform.', 'status' => 'active', 'start' => -35, 'end' => 28, 'budget' => 1850000],
            'atlas-system' => ['client' => 'atlas', 'name' => 'Brand System Refresh', 'description' => 'A focused refresh of the visual system and sales collateral.', 'status' => 'completed', 'start' => -150, 'end' => -55, 'budget' => 960000],
            'harbor-portal' => ['client' => 'harbor', 'name' => 'Patient Portal UX', 'description' => 'Research and interface design for the patient onboarding journey.', 'status' => 'active', 'start' => -22, 'end' => 45, 'budget' => 2240000],
            'field-campaign' => ['client' => 'field', 'name' => 'Autumn Stories Campaign', 'description' => 'Editorial landing pages and campaign assets for the autumn collection.', 'status' => 'active', 'start' => -14, 'end' => 24, 'budget' => 780000],
            'lumen-portfolio' => ['client' => 'lumen', 'name' => 'Architecture Portfolio', 'description' => 'A new portfolio experience centered on projects, process, and inquiries.', 'status' => 'on_hold', 'start' => -70, 'end' => 35, 'budget' => 1420000],
            'northwind-shop' => ['client' => 'northwind', 'name' => 'Wholesale Commerce Build', 'description' => 'Wholesale ordering experience with product storytelling and account flows.', 'status' => 'active', 'start' => -48, 'end' => 18, 'budget' => 2680000],
            'verdant-deck' => ['client' => 'verdant', 'name' => 'Investor Narrative', 'description' => 'Fundraising narrative, data visuals, and investor presentation system.', 'status' => 'completed', 'start' => -95, 'end' => -20, 'budget' => 890000],
            'verdant-site' => ['client' => 'verdant', 'name' => 'Analytics Marketing Site', 'description' => 'Conversion-focused website for the enterprise analytics product.', 'status' => 'active', 'start' => -10, 'end' => 52, 'budget' => 1760000],
        ];

        $projects = [];
        foreach ($records as $key => $record) {
            $projects[$key] = Project::create([
                'workspace_id' => $workspace->id,
                'client_id' => $clients[$record['client']]->id,
                'name' => $record['name'],
                'description' => $record['description'],
                'status' => $record['status'],
                'start_date' => $today->addDays($record['start']),
                'end_date' => $today->addDays($record['end']),
                'budget_cents' => $record['budget'],
                'currency_code' => 'EUR',
            ]);
        }

        return $projects;
    }

    /** @param array<string, Project> $projects */
    private function seedTasks(Workspace $workspace, array $projects): void
    {
        $today = CarbonImmutable::today();
        $records = [
            ['atlas-launch', 'Finalize launch messaging', 'Align the headline system with sales and product teams.', 4, 'high', 'in_progress'],
            ['atlas-launch', 'Build responsive landing page', 'Implement the approved launch page across desktop and mobile.', 11, 'high', 'todo'],
            ['atlas-launch', 'Prepare analytics plan', 'Document launch events, funnels, and reporting ownership.', 16, 'medium', 'todo'],
            ['atlas-system', 'Deliver brand guidelines', 'Package the final identity rules and component examples.', -58, 'medium', 'done'],
            ['atlas-system', 'Archive approved assets', 'Organize final exports and source files for handoff.', -55, 'low', 'done'],
            ['harbor-portal', 'Synthesize interview findings', 'Turn patient interviews into prioritized journey insights.', -3, 'high', 'done'],
            ['harbor-portal', 'Prototype onboarding flow', 'Create and test the high-fidelity onboarding sequence.', 7, 'high', 'in_progress'],
            ['harbor-portal', 'Accessibility review', 'Review navigation, form labels, contrast, and error states.', 19, 'medium', 'todo'],
            ['field-campaign', 'Select editorial photography', 'Confirm usage rights and campaign crops with the photo editor.', 3, 'medium', 'in_progress'],
            ['field-campaign', 'Design destination templates', 'Create reusable layouts for the seasonal destination stories.', 12, 'medium', 'todo'],
            ['lumen-portfolio', 'Confirm project shortlist', 'Agree on the twelve projects for the first portfolio release.', -9, 'high', 'todo'],
            ['lumen-portfolio', 'Collect project photography', 'Request high-resolution imagery and project credits.', 22, 'low', 'todo'],
            ['northwind-shop', 'Map wholesale checkout', 'Document pricing, minimum order, and fulfillment decisions.', -1, 'high', 'in_progress'],
            ['northwind-shop', 'Implement product catalog', 'Build category, search, and product detail experiences.', 8, 'high', 'in_progress'],
            ['northwind-shop', 'Write fulfillment emails', 'Draft concise order, dispatch, and delivery notifications.', 15, 'low', 'todo'],
            ['verdant-deck', 'Polish market sizing slides', 'Refine the market model and supporting narrative.', -24, 'medium', 'done'],
            ['verdant-deck', 'Export investor presentation', 'Deliver presentation, PDF, and reusable chart assets.', -20, 'low', 'done'],
            ['verdant-site', 'Define enterprise use cases', 'Shape three use cases around operations, reporting, and risk.', 5, 'high', 'in_progress'],
            ['verdant-site', 'Design pricing narrative', 'Clarify the sales-led pricing model without publishing rates.', 14, 'medium', 'todo'],
            ['verdant-site', 'Plan launch QA', 'Create the cross-browser, content, SEO, and analytics checklist.', 31, 'low', 'todo'],
        ];

        foreach ($records as [$projectKey, $title, $description, $deadline, $priority, $status]) {
            Task::create([
                'workspace_id' => $workspace->id,
                'project_id' => $projects[$projectKey]->id,
                'title' => $title,
                'description' => $description,
                'deadline' => $today->addDays($deadline),
                'priority' => $priority,
                'status' => $status,
            ]);
        }
    }

    /** @param array<string, Client> $clients */
    private function seedInvoices(User $user, array $clients): void
    {
        $today = CarbonImmutable::today();
        $records = [
            ['atlas', -82, -68, 'paid', 'Brand system strategy and delivery', [['Brand strategy sprint', 1, 420000, 0, 19], ['Visual identity system', 1, 540000, 0, 19]]],
            ['verdant', -58, -44, 'paid', 'Investor narrative engagement', [['Narrative and message architecture', 1, 360000, 0, 19], ['Investor deck design', 1, 480000, 5, 19]]],
            ['harbor', -31, -17, 'paid', 'Patient research and journey mapping', [['Patient interview synthesis', 1, 280000, 0, 19], ['Experience map and recommendations', 1, 340000, 0, 19]]],
            ['northwind', -24, -10, 'sent', 'Wholesale commerce milestone', [['Commerce UX and interaction design', 1, 510000, 0, 19], ['Design system implementation', 1, 390000, 0, 19]]],
            ['lumen', -40, -12, 'sent', 'Portfolio discovery and content direction', [['Discovery workshop', 1, 160000, 0, 19], ['Content structure and art direction', 1, 245000, 0, 19]]],
            ['field', -12, 2, 'sent', 'Autumn campaign production', [['Campaign concept development', 1, 210000, 0, 19], ['Editorial page templates', 3, 95000, 0, 19]]],
            ['atlas', -5, 9, 'draft', 'Product launch website milestone', [['Launch page design', 1, 320000, 0, 19], ['Responsive frontend build', 1, 460000, 0, 19]]],
            ['verdant', -2, 12, 'draft', 'Marketing site discovery', [['Stakeholder workshop', 2, 85000, 0, 19], ['Website strategy brief', 1, 145000, 0, 19]]],
            ['northwind', -110, -96, 'cancelled', 'Initial retail concept exploration', [['Retail concept workshop', 1, 125000, 0, 19], ['Concept directions', 2, 90000, 0, 19]]],
        ];

        foreach ($records as [$clientKey, $issueOffset, $dueOffset, $status, $notes, $items]) {
            $invoice = $this->invoiceService->create($user, [
                'client_id' => $clients[$clientKey]->id,
                'issue_date' => $today->addDays($issueOffset)->toDateString(),
                'due_date' => $today->addDays($dueOffset)->toDateString(),
                'currency_code' => 'EUR',
                'notes' => $notes,
                'items' => array_map(fn (array $item) => [
                    'description' => $item[0],
                    'quantity' => $item[1],
                    'unit_price_cents' => $item[2],
                    'discount_rate' => $item[3],
                    'tax_rate' => $item[4],
                ], $items),
            ]);

            if ($status === 'paid') {
                $invoice = $this->invoiceService->update($user, $invoice, ['status' => 'sent']);
                $this->invoiceService->update($user, $invoice, ['status' => 'paid']);
            } elseif ($status !== 'draft') {
                $this->invoiceService->update($user, $invoice, ['status' => $status]);
            }
        }
    }
}
