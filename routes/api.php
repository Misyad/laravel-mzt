<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\admin\C_transaksi;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TicketController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [ApiController::class, 'login']);
Route::post('/transaksi/pembayaran/hendle-payment', [C_transaksi::class, 'payment_hendler']);

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

// Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

// Auth
    Route::get('/user', [ApiController::class, 'user']);
    Route::post('/logout', [ApiController::class, 'logout']);

    // Phase 1 — Alumni digital identity
    Route::get('/me', [ApiController::class, 'me']);
    Route::get('/profile', [ApiController::class, 'profileGet']);
    Route::put('/profile', [ApiController::class, 'profileUpdateJson']);
    Route::put('/password', [ApiController::class, 'changePassword']);
    Route::get('/id-card', [ApiController::class, 'idCard']);

    // Account management
    // NOTE: the literal /members/bulk-account MUST be declared before
    // /members/{id} so it is not captured by the parameter route.
    Route::post('/members/bulk-account', [ApiController::class, 'bulkGenerate']);
    Route::post('/members/{id}/account', [ApiController::class, 'generateAccount']);
    Route::put('/members/{id}/account', [ApiController::class, 'resetAccount']);
    Route::put('/members/{id}/account/status', [ApiController::class, 'setAccountStatus']);

    // Dashboard
    Route::get('/dashboard/stats', [ApiController::class, 'dashboardStats']);
    Route::get('/dashboard/calendar', [ApiController::class, 'dashboardCalendar']);
    Route::get('/dashboard/events', [ApiController::class, 'dashboardEvents']);

    // Members
    Route::get('/members', [ApiController::class, 'membersIndex']);
    Route::get('/members/{id}', [ApiController::class, 'membersShow']);
    Route::post('/members', [ApiController::class, 'membersStore']);
    Route::post('/members/{id}', [ApiController::class, 'membersUpdate']);
    Route::delete('/members/{id}', [ApiController::class, 'membersDestroy']);

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

    // Phase 2B — Payment Engine (Sprint 2)
    Route::post('/orders/{uuid}/payment', [PaymentController::class, 'upload'])
        ->middleware('throttle:10,1');
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

    // News
    Route::get('/news', [ApiController::class, 'newsIndex']);
    Route::get('/news/{id}', [ApiController::class, 'newsShow']);
    Route::post('/news', [ApiController::class, 'newsStore']);
    Route::post('/news/{id}', [ApiController::class, 'newsUpdate']);
    Route::delete('/news/{id}', [ApiController::class, 'newsDestroy']);

    // Attendance
    Route::get('/attendance/{eventId}/{tanggalId}', [ApiController::class, 'attendanceIndex']);
    Route::post('/attendance', [ApiController::class, 'attendanceStore']);

    // Transactions
    Route::get('/transactions/{eventId}', [ApiController::class, 'transactionsIndex']);

    // Content
    Route::get('/carousel', [ApiController::class, 'carouselIndex']);
    Route::post('/carousel/{id}', [ApiController::class, 'carouselUpdate']);
    Route::post('/info/pesantren', [ApiController::class, 'infoPesantrenUpdate']);
    Route::post('/info/mzt', [ApiController::class, 'infoMztUpdate']);

    // Activity Log
        Route::get('/activity-log', [ApiController::class, 'activityLogIndex']);
    Route::get('/activity-log/{userId}', [ApiController::class, 'activityLogUser']);

    // Profile
    Route::post('/profile', [ApiController::class, 'profileUpdate']);
});

// KTA Card (public HTML view)
Route::get('/kta/{id}', [ApiController::class, 'ktaView']);
