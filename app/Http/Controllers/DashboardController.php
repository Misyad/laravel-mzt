<?php

namespace App\Http\Controllers;

use App\Contracts\DashboardServiceInterface;
use App\DTO\DashboardFilter;
use App\Http\Resources\Dashboard\OverviewResource;
use App\Http\Resources\Dashboard\PaymentSummaryResource;
use App\Http\Resources\Dashboard\RegistrationSummaryResource;
use App\Http\Resources\Dashboard\RevenueSummaryResource;
use App\Support\Dashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Finance Dashboard API (Sprint 5A).
 *
 * Thin controller (read-only): maps the Request into a DashboardFilter DTO,
 * enforces module-level dashboard authorization via Gate, calls the
 * DashboardServiceInterface, and returns the result wrapped in an API Resource.
 * No business logic and no manual JSON building live here.
 *
 * Endpoints (all inside auth:sanctum):
 *  - GET /dashboard/finance/overview      (viewOverview)
 *  - GET /dashboard/finance/registration  (viewOverview)
 *  - GET /dashboard/finance/revenue       (viewRevenue)
 *  - GET /dashboard/finance/payments      (viewPayment)
 */
class DashboardController extends Controller
{
    public function __construct(
        protected DashboardServiceInterface $dashboard,
    ) {
    }

    public function overview(Request $request)
    {
        $this->authorizeDashboard($request, 'viewOverview');

        return response()->json([
            'success' => true,
            'data' => new OverviewResource($this->dashboard->overview($this->map($request))),
        ]);
    }

    public function registration(Request $request)
    {
        $this->authorizeDashboard($request, 'viewOverview');

        return response()->json([
            'success' => true,
            'data' => new RegistrationSummaryResource($this->dashboard->registrationSummary($this->map($request))),
        ]);
    }

    public function revenue(Request $request)
    {
        $this->authorizeDashboard($request, 'viewRevenue');

        return response()->json([
            'success' => true,
            'data' => new RevenueSummaryResource($this->dashboard->revenueSummary($this->map($request))),
        ]);
    }

    public function payments(Request $request)
    {
        $this->authorizeDashboard($request, 'viewPayment');

        return response()->json([
            'success' => true,
            'data' => new PaymentSummaryResource($this->dashboard->paymentSummary($this->map($request))),
        ]);
    }

    /**
     * Authorize a module-level dashboard ability for the current user.
     */
    private function authorizeDashboard(Request $request, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, Dashboard::class);
    }

    /**
     * Map the incoming Request into the pure DashboardFilter DTO.
     */
    private function map(Request $request): DashboardFilter
    {
        return new DashboardFilter(
            start: $request->input('start') ?: null,
            end: $request->input('end') ?: null,
            eventId: $request->input('event_id') ? $request->integer('event_id') : null,
            status: $request->input('status') ?: null,
        );
    }
}