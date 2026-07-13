<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AttendanceRecapController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\Marketplace\BrandController;
use App\Http\Controllers\Marketplace\DiscountController;
use App\Http\Controllers\Marketplace\MarketplaceDashboardController;
use App\Http\Controllers\Marketplace\ProductController;
use App\Http\Controllers\Marketplace\StoreController;
use App\Http\Controllers\Marketplace\TaskController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\Service\DashboardController as ServiceDashboardController;
use App\Http\Controllers\Service\KpiController;
use App\Http\Controllers\Service\TicketController;
use App\Http\Controllers\Service\TrackingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH — login/logout manual
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'attempt'])
        ->middleware('throttle:10,1')->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| PUBLIK — customer lacak servis TANPA login
|--------------------------------------------------------------------------
*/
Route::prefix('track')->name('track.')->group(function () {
    Route::get('/',         [TrackingController::class, 'form'])->name('form');
    Route::post('/',        [TrackingController::class, 'lookup'])->name('lookup');
    Route::get('/{ticket}', [TrackingController::class, 'show'])->name('show');
});

/*
|--------------------------------------------------------------------------
| ROOT — arahkan tergantung status login
| Guest & orang belum login: hindari langsung expose dashboard internal,
| arahkan ke tracking. CATATAN: ini bukan proteksi keamanan — cuma UX.
| /login, /dashboard, dll tetap bisa diakses langsung kalau diketik manual.
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('track.form');
});

/*
|--------------------------------------------------------------------------
| INTERNAL — semua staf yang login
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // ---------------- ABSENSI (semua staf) ----------------
    Route::get('/attendance',            [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in',  [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');
    Route::get('/attendance/my-recap', [AttendanceRecapController::class, 'me'])->name('attendance.myrecap');
    // Selfie ulang atas permintaan CEO — tanpa geofence, momen aslinya sudah lewat.
    Route::post('/attendance/{attendance}/retake', [AttendanceController::class, 'submitRetake'])->name('attendance.retake');

    // ---------------- IZIN / SAKIT / CUTI ----------------
    Route::get('/leaves',           [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::post('/leaves',          [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::delete('/leaves/{leave}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');

    // Approval — kepala toko ATAU CEO (permission non-CEO pertama di sistem)
    Route::middleware('manager')->group(function () {
        Route::get('/leaves/manage',         [LeaveApprovalController::class, 'index'])->name('leaves.manage');
        Route::post('/leaves/{leave}/decide', [LeaveApprovalController::class, 'decide'])->name('leaves.decide');
    });

    // ---------------- TUGAS MARKETPLACE (PIC toko + CEO) ----------------
    Route::get('/marketplace/tasks',                  [TaskController::class, 'index'])->name('marketplace.tasks.index');
    Route::post('/marketplace/tasks/{task}/complete', [TaskController::class, 'complete'])->name('marketplace.tasks.complete');
    Route::post('/marketplace/tasks/{task}/undo',     [TaskController::class, 'undo'])->name('marketplace.tasks.undo');
    Route::post('/marketplace/tasks/{task}/pin',      [TaskController::class, 'togglePin'])->name('marketplace.tasks.pin');
    Route::post('/marketplace/tasks/{task}/revise',   [TaskController::class, 'requestRevision'])->name('marketplace.tasks.revise');

    // ---------------- MODUL SERVICE ----------------
    Route::prefix('service')->name('service.')->group(function () {

        Route::get('/', [ServiceDashboardController::class, 'index'])->name('dashboard');
        // CATATAN FASE PERMISSION: KPI berisi data finansial (omzet, modal, margin).
        // Dulu dikunci role:kepala_toko — sekarang terbuka semua staf sesuai keputusan
        // authorization rata. Kalau Thomas mau kunci lagi: tambah middleware di sini.
        Route::get('/kpi', [KpiController::class, 'index'])->name('kpi');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');

        // PENTING: /create HARUS di atas /{ticket} — kalau kebalik,
        // kata "create" dianggap ID tiket dan halaman intake jadi 404.
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets',       [TicketController::class, 'store'])->name('tickets.store');

        Route::get('/tickets/{ticket}',         [TicketController::class, 'show'])->name('tickets.show');
        Route::get('/tickets/{ticket}/receipt', [TicketController::class, 'receipt'])->name('tickets.receipt');

        Route::post('/tickets/{ticket}/transition', [TicketController::class, 'transition'])->name('tickets.transition');
        Route::post('/tickets/{ticket}/notify',     [TicketController::class, 'notify'])->name('tickets.notify');
        Route::post('/tickets/{ticket}/assign',     [TicketController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{ticket}/parts',      [TicketController::class, 'storePart'])->name('tickets.parts.store');
        Route::delete('/tickets/{ticket}/parts/{partId}', [TicketController::class, 'destroyPart'])->name('tickets.parts.destroy');
    });

    // Leaderboard sosmed — terbuka semua staf (motivasi/kompetisi)
    Route::get('/sosmed/leaderboard', [\App\Http\Controllers\Sosmed\ReportController::class, 'leaderboard'])->name('sosmed.leaderboard');

    // ---------------- SOSMED (PIC Sosmed + CEO) ----------------
    Route::middleware('sosmed')->prefix('sosmed')->name('sosmed.')->group(function () {
        Route::get('/videos',               [\App\Http\Controllers\Sosmed\VideoController::class, 'index'])->name('videos.index');
        Route::get('/videos/create',        [\App\Http\Controllers\Sosmed\VideoController::class, 'create'])->name('videos.create');
        Route::post('/videos',              [\App\Http\Controllers\Sosmed\VideoController::class, 'store'])->name('videos.store');
        Route::get('/videos/{video}/edit',  [\App\Http\Controllers\Sosmed\VideoController::class, 'edit'])->name('videos.edit');
        Route::put('/videos/{video}',       [\App\Http\Controllers\Sosmed\VideoController::class, 'update'])->name('videos.update');
        Route::delete('/videos/{video}',    [\App\Http\Controllers\Sosmed\VideoController::class, 'destroy'])->name('videos.destroy');
        Route::get('/metrics',  [\App\Http\Controllers\Sosmed\MetricController::class, 'index'])->name('metrics.index');
        Route::post('/metrics', [\App\Http\Controllers\Sosmed\MetricController::class, 'store'])->name('metrics.store');
        Route::post('/postings/{posting}/refresh', [\App\Http\Controllers\Sosmed\MetricController::class, 'refresh'])->name('postings.refresh');
        Route::delete('/snapshots/{snapshot}', [\App\Http\Controllers\Sosmed\MetricController::class, 'destroySnapshot'])->name('snapshots.destroy');
        Route::get('/report',   [\App\Http\Controllers\Sosmed\ReportController::class, 'index'])->name('report');
        Route::post('/targets', [\App\Http\Controllers\Sosmed\ReportController::class, 'storeTarget'])->name('targets.store');
    });

    // ---------------- KHUSUS CEO ----------------
    Route::middleware('ceo')->group(function () {

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',            [UserManagementController::class, 'index'])->name('index');
            Route::get('/create',      [UserManagementController::class, 'create'])->name('create');
            Route::post('/',           [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}',      [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}',   [UserManagementController::class, 'destroy'])->name('destroy');
        });

        Route::get('/attendance/schedules',        [WorkScheduleController::class, 'index'])->name('attendance.schedules');
        Route::put('/attendance/schedules/{user}', [WorkScheduleController::class, 'upsert'])->name('attendance.schedules.upsert');
        
        Route::get('/attendance/recap',        [AttendanceRecapController::class, 'index'])->name('attendance.recap');
        Route::get('/attendance/recap/{user}', [AttendanceRecapController::class, 'show'])->name('attendance.recap.show');

        // Koreksi absen — CEO only. Data asli tidak ditimpa diam-diam: setiap
        // perubahan menyimpan snapshot before/after + alasan wajib di audit trail.
        Route::get('/attendance/corrections/{attendance}/edit', [AttendanceCorrectionController::class, 'edit'])->name('attendance.corrections.edit');
        Route::put('/attendance/corrections/{attendance}',      [AttendanceCorrectionController::class, 'update'])->name('attendance.corrections.update');
        Route::get('/attendance/corrections/create/{user}',     [AttendanceCorrectionController::class, 'create'])->name('attendance.corrections.create');
        Route::post('/attendance/corrections/{user}',           [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');
        Route::post('/attendance/corrections/{attendance}/retake', [AttendanceCorrectionController::class, 'requestRetake'])->name('attendance.corrections.retake');

        Route::get('/branches',          [BranchSettingController::class, 'index'])->name('branches.index');
        Route::put('/branches/{branch}', [BranchSettingController::class, 'update'])->name('branches.update');

        // Master platform sosmed. Prefix 'sosmed-platforms' (bukan nested di /sosmed)
        // supaya tidak ikut tertangkap middleware 'sosmed' yang scope-nya beda.
        Route::prefix('sosmed-platforms')->name('sosmed.platforms.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Sosmed\PlatformController::class, 'index'])->name('index');
            Route::post('/',             [\App\Http\Controllers\Sosmed\PlatformController::class, 'store'])->name('store');
            Route::put('/{platform}',    [\App\Http\Controllers\Sosmed\PlatformController::class, 'update'])->name('update');
            Route::delete('/{platform}', [\App\Http\Controllers\Sosmed\PlatformController::class, 'destroy'])->name('destroy');
        });

        Route::get('/holidays',           [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('/holidays',          [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::prefix('marketplace')->name('marketplace.')->group(function () {
            Route::get('/dashboard',                       [MarketplaceDashboardController::class, 'index'])->name('dashboard');
            Route::post('/dashboard/{store}/generate',     [MarketplaceDashboardController::class, 'generateBacklog'])->name('dashboard.generate');

            Route::get('/stores',              [StoreController::class, 'index'])->name('stores.index');
            Route::post('/stores',             [StoreController::class, 'store'])->name('stores.store');
            // PENTING: path literal (trash, clear) HARUS di atas {id}/{store}
            Route::get('/stores/trash',          [StoreController::class, 'trash'])->name('stores.trash');
            Route::delete('/stores/trash/clear', [StoreController::class, 'clearTrash'])->name('stores.trash.clear');
            Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])->name('stores.edit');
            Route::put('/stores/{store}',      [StoreController::class, 'update'])->name('stores.update');
            Route::patch('/stores/{id}/restore', [StoreController::class, 'restore'])->name('stores.restore');
            Route::delete('/stores/{store}',     [StoreController::class, 'destroy'])->name('stores.destroy');

            Route::get('/brands',              [BrandController::class, 'index'])->name('brands.index');
            Route::post('/brands',             [BrandController::class, 'store'])->name('brands.store');
            // PENTING: path literal (trash, clear) HARUS di atas {id}/{brand}
            Route::get('/brands/trash',          [BrandController::class, 'trash'])->name('brands.trash');
            Route::delete('/brands/trash/clear', [BrandController::class, 'clearTrash'])->name('brands.trash.clear');
            Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
            Route::put('/brands/{brand}',      [BrandController::class, 'update'])->name('brands.update');
            Route::patch('/brands/{id}/restore', [BrandController::class, 'restore'])->name('brands.restore');
            Route::delete('/brands/{brand}',     [BrandController::class, 'destroy'])->name('brands.destroy');

            // PENTING: /products/create & /products/import HARUS di atas /products/{product}
            Route::get('/products',                   [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create',            [ProductController::class, 'create'])->name('products.create');
            Route::post('/products',                  [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/import',            [ProductController::class, 'importForm'])->name('products.import.form');
            Route::post('/products/import',           [ProductController::class, 'import'])->name('products.import');
            Route::get('/products/export',            [ProductController::class, 'export'])->name('products.export');
            Route::get('/products/trash',             [ProductController::class, 'trash'])->name('products.trash');
            Route::delete('/products/trash/clear',    [ProductController::class, 'clearTrash'])->name('products.trash.clear');
            Route::get('/products/{product}/edit',    [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}',         [ProductController::class, 'update'])->name('products.update');
            Route::patch('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
            Route::patch('/products/{id}/restore',    [ProductController::class, 'restore'])->name('products.restore');
            Route::delete('/products/{product}',      [ProductController::class, 'destroy'])->name('products.destroy');

            Route::get('/discounts',               [DiscountController::class, 'index'])->name('discounts.index');
            Route::post('/discounts',              [DiscountController::class, 'store'])->name('discounts.store');
            Route::delete('/discounts/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');
        });
    });

    // ---------------- KEUANGAN (CEO + Kepala Keuangan) ----------------
    Route::middleware('finance')->group(function () {
        Route::get('/payroll',                [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll/{user}/issue',  [PayrollController::class, 'issue'])->name('payroll.issue');
        Route::get('/payroll/slip/{payslip}', [PayrollController::class, 'show'])->name('payroll.show');
    });
});