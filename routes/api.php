<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\admin\C_transaksi;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventPaymentController;
use App\Http\Controllers\OperationalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\admin\C_AuditTimeline;
use App\Http\Controllers\Public\KtaLookupController;
use App\Http\Controllers\Public\KtaPrintRequestController as PublicKtaPrintRequestController;
use App\Http\Controllers\Public\PaymenkuWebhookController;
use App\Http\Controllers\KtaPrintRequestController as AdminKtaPrintRequestController;
use App\Http\Controllers\KtaCardController;
use App\Http\Controllers\KtaPriceSettingController;
use App\Http\Controllers\AccountSetupController;
use App\Http\Controllers\ApplicantAuthController;
use App\Http\Controllers\MemberApplicationAdminController;
use App\Http\Controllers\OwnKtaPrintRequestController;
use App\Http\Controllers\Public\AccountActivationController;
use App\Http\Controllers\Public\MemberApplicationController;
use App\Http\Controllers\Public\PasswordResetController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [ApiController::class, 'login']);
Route::post('/transaksi/pembayaran/hendle-payment', [C_transaksi::class, 'payment_hendler']);

Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function () {
    // Public — "Cek Status KTA" (rate-limited, anti-enumeration, no PII in response)
    Route::middleware('throttle:kta-check')->post('/public/kta/check', [KtaLookupController::class, 'check']);
    Route::middleware('throttle:kta-verify')->post('/public/kta/verify', [KtaLookupController::class, 'verify']);

    // Public — physical KTA print request (identity from verified print token)
    Route::middleware('throttle:kta-print-request')->group(function () {
        Route::get('/public/kta/print-request', [PublicKtaPrintRequestController::class, 'show']);
        Route::post('/public/kta/print-request', [PublicKtaPrintRequestController::class, 'store']);
    });

    Route::middleware('throttle:member-activation-check')->post('/public/account-activation/check', [AccountActivationController::class, 'check']);
    Route::middleware('throttle:member-activation-verify')->post('/public/account-activation/verify', [AccountActivationController::class, 'verify']);
    Route::middleware('throttle:member-password-forgot')->post('/public/password/forgot', [PasswordResetController::class, 'forgot']);
    Route::middleware('throttle:member-password-reset')->post('/public/password/reset', [PasswordResetController::class, 'reset']);
});

Route::middleware('throttle:member-applications')->post('/public/member-applications', [MemberApplicationController::class, 'store']);
Route::middleware('throttle:applicant-login')->post('/applicant/login', [ApplicantAuthController::class, 'login']);
Route::middleware('auth.applicant')->prefix('applicant')->group(function () {
    Route::get('/me', [ApplicantAuthController::class, 'me']);
    Route::post('/logout', [ApplicantAuthController::class, 'logout']);
    Route::middleware('throttle:applicant-email')->group(function () {
        Route::post('/email/resend', [MemberApplicationController::class, 'resend']);
        Route::post('/email/verify', [MemberApplicationController::class, 'verify']);
    });
    Route::put('/application', [MemberApplicationController::class, 'update']);
});

// Paymenku webhook (signature-authenticated; no session/CSRF)
Route::post('/webhooks/paymenku', [PaymenkuWebhookController::class, 'handle']);

// Public data (no auth required)
Route::get('/info/pesantren', [ApiController::class, 'infoPesantren']);
Route::get('/info/mzt', [ApiController::class, 'infoMzt']);
Route::get('/public/events', [ApiController::class, 'eventsIndex']);
Route::get('/public/events/{id}', [ApiController::class, 'eventsShow']);
Route::get('/public/news', [ApiController::class, 'newsIndex']);
Route::get('/public/news/{id}', [ApiController::class, 'newsShow']);
Route::get('/public/carousel', [ApiController::class, 'carouselIndex']);
Route::get('/public/stats', [ApiController::class, 'publicStats']);
Route::post('/public/contact', [ApiController::class, 'contactStore']);

// Protected routes (require Sanctum token + an ACTIVE account per request).
Route::middleware(['auth:sanctum', 'check-active'])->group(function () {

    Route::get('/user', [ApiController::class, 'user']);
    Route::get('/me', [ApiController::class, 'me']);
    Route::post('/logout', [ApiController::class, 'logout']);
    Route::middleware('throttle:member-account-setup')->group(function () {
        Route::post('/account/setup/email', [AccountSetupController::class, 'sendEmail']);
        Route::post('/account/setup/email/verify', [AccountSetupController::class, 'verifyEmail']);
        Route::post('/account/setup/complete', [AccountSetupController::class, 'complete']);
    });

    Route::middleware('account-setup-complete')->group(function () {
        Route::put('/password', [ApiController::class, 'changePassword']);

        Route::middleware('password-changed')->group(function () {
        Route::get('/profile', [ApiController::class, 'profileGet']);
        Route::put('/profile', [ApiController::class, 'profileUpdateJson']);
        Route::get('/id-card', [ApiController::class, 'idCard']);
        Route::get('/me/kta/print-request', [OwnKtaPrintRequestController::class, 'show']);
        Route::post('/me/kta/print-request', [OwnKtaPrintRequestController::class, 'store'])
            ->middleware('throttle:kta-print-request');

        Route::get('/members/account-reset-audit', [ApiController::class, 'accountResetAudit']);
        Route::put('/members/{id}/account', [ApiController::class, 'resetAccount'])->whereNumber('id');
        Route::put('/members/{id}/status', [ApiController::class, 'setAccountStatus'])->whereNumber('id');
        Route::put('/members/{id}/account/status', [ApiController::class, 'setAccountStatus'])->whereNumber('id');

// Dashboard
    Route::get('/dashboard/stats', [ApiController::class, 'dashboardStats']);
    Route::get('/dashboard/calendar', [ApiController::class, 'dashboardCalendar']);
    Route::get('/dashboard/events', [ApiController::class, 'dashboardEvents']);

    // Sprint 5A — Finance Dashboard (read-only)
    Route::get('/dashboard/finance/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/finance/registration', [DashboardController::class, 'registration']);
    Route::get('/dashboard/finance/revenue', [DashboardController::class, 'revenue']);
    Route::get('/dashboard/finance/payments', [DashboardController::class, 'payments']);

    // Sprint 5B.1 — Ticket & Operational Monitoring (read-only)
    Route::get('/dashboard/finance/tickets', [DashboardController::class, 'tickets']);
    Route::get('/dashboard/finance/operational', [DashboardController::class, 'operational']);

    // Members
    Route::get('/members', [ApiController::class, 'membersIndex']);
        Route::get('/members/role-targets', [ApiController::class, 'memberRoleTargetsIndex']);
        Route::get('/members/{id}/roles', [ApiController::class, 'memberRolesShow'])->whereNumber('id');
        Route::put('/members/{id}/roles', [ApiController::class, 'memberRolesUpdate'])->whereNumber('id');
    Route::get('/members/{id}', [ApiController::class, 'membersShow'])->whereNumber('id');
    Route::post('/members/{id}', [ApiController::class, 'membersUpdate'])->whereNumber('id');

    // Events
    Route::get('/events', [ApiController::class, 'eventsIndex']);
    Route::get('/events/{id}', [ApiController::class, 'eventsShow']);
    Route::post('/events', [ApiController::class, 'eventsStore']);
    Route::post('/events/{id}', [ApiController::class, 'eventsUpdate']);
    Route::delete('/events/{id}', [ApiController::class, 'eventsDestroy']);
    Route::get('/events/{id}/tanggal', [ApiController::class, 'eventTanggal']);

    // Phase 2A — Registration & Orders
    Route::post('/events/{id}/register', [ApiController::class, 'registerEvent']);
    Route::get('/my-orders', [ApiController::class, 'myOrders']);
    Route::get('/orders/{uuid}', [ApiController::class, 'orderShow']);
    Route::post('/orders/{uuid}/checkout', [EventPaymentController::class, 'checkout'])
        ->middleware('throttle:10,1');

    // Phase 2B — Payment Engine (Sprint 2)
    Route::post('/orders/{uuid}/payment', [PaymentController::class, 'upload'])
        ->middleware('throttle:10,1');
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{uuid}', [PaymentController::class, 'show']);
    Route::get('/payments/{uuid}/proof', [PaymentController::class, 'proof']);
    Route::put('/payments/{uuid}/verify', [PaymentController::class, 'verify']);
    Route::get('/my-payments', [PaymentController::class, 'myPayments']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // Phase 2B — Ticket Engine (Sprint 3)
    Route::get('/orders/{uuid}/ticket', [TicketController::class, 'myTicket']);
    Route::get('/tickets/{uuid}', [TicketController::class, 'show']);
    Route::get('/tickets/{uuid}/download', [TicketController::class, 'download']);
    Route::post('/tickets/{uuid}/reissue', [TicketController::class, 'reissue']);
    Route::delete('/tickets/{uuid}', [TicketController::class, 'revoke']);

    // Sprint 4 — Communication Engine (PRD §20 / ADR-016)
    Route::get('/notifications', [CommunicationController::class, 'index']);
    Route::put('/notifications/read', [CommunicationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [CommunicationController::class, 'markAllRead']);
    Route::get('/communication-logs', [CommunicationController::class, 'communicationLogs']);

    // News
    Route::get('/news', [ApiController::class, 'newsIndex']);
    Route::get('/news/{id}', [ApiController::class, 'newsShow']);
    Route::post('/news', [ApiController::class, 'newsStore']);
    Route::post('/news/{id}', [ApiController::class, 'newsUpdate']);
    Route::delete('/news/{id}', [ApiController::class, 'newsDestroy']);

    // Attendance
    Route::get('/attendance/{eventId}/{tanggalId}', [ApiController::class, 'attendanceIndex']);
    Route::post('/attendance', [ApiController::class, 'attendanceStore']);

    // Phase 2C — QR Check-In Foundation (PRD §17.8)
    Route::post('/checkin/lookup', [CheckInController::class, 'lookup'])
        ->middleware('throttle:120,1');
    Route::post('/checkin/onsite', [CheckInController::class, 'onsite'])
        ->middleware('throttle:60,1');
    Route::post('/checkin', [CheckInController::class, 'store'])
        ->middleware('throttle:60,1');

    // Transactions
    Route::get('/transactions/{eventId}', [ApiController::class, 'transactionsIndex']);

    // Phase 2D — EMS Operational Management (read-only)
    Route::get('/dashboard/operations/events', [OperationalController::class, 'events']);
    Route::get('/dashboard/operations/events/{event}/attendees', [OperationalController::class, 'attendees']);
    Route::get('/dashboard/operations/events/{event}/attendance', [OperationalController::class, 'attendance']);
    Route::get('/dashboard/operations/events/{event}/gates', [OperationalController::class, 'gates']);

    // Content
    Route::get('/carousel', [ApiController::class, 'carouselIndex']);
    Route::post('/carousel/{id}', [ApiController::class, 'carouselUpdate']);
    Route::post('/info/pesantren', [ApiController::class, 'infoPesantrenUpdate']);
    Route::post('/info/mzt', [ApiController::class, 'infoMztUpdate']);

    // Activity Log
        Route::get('/activity-log', [ApiController::class, 'activityLogIndex']);
    Route::get('/activity-log/{userId}', [ApiController::class, 'activityLogUser']);

    // M-05 — Unified Audit Timeline
    Route::get('/audit-timeline', [C_AuditTimeline::class, 'index']);
    Route::get('/audit-timeline/data', [C_AuditTimeline::class, 'data']);

    // KTA physical print queue (admin/verifier)
    Route::get('/kta/print-requests', [AdminKtaPrintRequestController::class, 'index']);
    Route::get('/kta/print-requests/{id}/card', [KtaCardController::class, 'fromPrintRequest']);
    Route::get('/kta/print-requests/{id}', [AdminKtaPrintRequestController::class, 'show']);
    Route::put('/kta/print-requests/{id}/status', [AdminKtaPrintRequestController::class, 'updateStatus']);
    Route::get('/kta/settings/price', [KtaPriceSettingController::class, 'show']);
    Route::put('/kta/settings/price', [KtaPriceSettingController::class, 'update']);

    Route::get('/kta/cards', [KtaCardController::class, 'index']);
    Route::get('/kta/cards/{id}', [KtaCardController::class, 'show']);

        // Profile
        Route::post('/profile', [ApiController::class, 'profileUpdate']);

        Route::get('/member-applications', [MemberApplicationAdminController::class, 'index']);
        Route::get('/member-applications/{uuid}', [MemberApplicationAdminController::class, 'show']);
        Route::put('/member-applications/{uuid}/under-review', [MemberApplicationAdminController::class, 'underReview']);
        Route::put('/member-applications/{uuid}/approve', [MemberApplicationAdminController::class, 'approve']);
        Route::put('/member-applications/{uuid}/reject', [MemberApplicationAdminController::class, 'reject']);
        });
    });
});
