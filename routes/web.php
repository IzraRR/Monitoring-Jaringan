<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\BandwidthController;
use App\Http\Controllers\PembayaranController;
use App\Http\Controllers\LogAktivitasController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PasswordController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/deploy-migrate', function() {
    if (request('key') !== 'monitoring123') {
        abort(403, 'Unauthorized access.');
    }
    
    try {
        // Run migrations
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrationOutput = \Illuminate\Support\Facades\Artisan::output();
        
        // Run seeders
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $seedOutput = \Illuminate\Support\Facades\Artisan::output();
        
        // Fix role and name for kepsek to Kepsek (for requirements)
        \DB::table('admin')->where('username', 'kepsek')->update([
            'peran' => 'Kepsek',
            'nama_lengkap' => 'Kepala Sekolah'
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Migration, seeding, and role assignment completed!',
            'migration' => explode("\n", trim($migrationOutput)),
            'seeding' => explode("\n", trim($seedOutput))
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => explode("\n", trim($e->getTraceAsString()))
        ], 500);
    }
});

Route::get('/', function () {
    if (session('status_login') === true) {
        $role = session('admin_role');
        if ($role === 'Admin') {
            return redirect()->route('dashboard');
        }
        return redirect()->route('laporan.index');
    }
    return redirect()->route('login.form');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware(['admin.auth', 'admin.role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('pelanggan', PelangganController::class)->except(['show']);
    Route::post('/pelanggan/sinkronisasi', [PelangganController::class, 'syncMikrotik'])->name('pelanggan.sync');
    Route::post('/pelanggan/{pelanggan}/toggle-lock', [PelangganController::class, 'toggleLock'])->name('pelanggan.toggle-lock');
    Route::post('/pelanggan/{pelanggan}/disconnect', [PelangganController::class, 'disconnect'])->name('pelanggan.disconnect');
    Route::resource('bandwidth', BandwidthController::class)->except(['show']);
    Route::resource('pembayaran', PembayaranController::class)->except(['show']);
    Route::post('/pembayaran/notifikasi/kirim', [PembayaranController::class, 'sendNotifications'])->name('pembayaran.send-notifications');
    Route::get('/pembayaran/{id}/struk', [PembayaranController::class, 'cetakStruk'])->name('pembayaran.struk');
    Route::get('/log', [LogAktivitasController::class, 'index'])->name('log.index');
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/cetak', [LaporanController::class, 'cetakPdf'])->name('laporan.cetak');
    Route::get('/laporan/export-excel', [LaporanController::class, 'exportExcel'])->name('laporan.export-excel');
    Route::post('/laporan/kirim-owner', [LaporanController::class, 'kirimOwner'])->name('laporan.kirim-owner');
    Route::get('/dashboard/realtime-stats', [DashboardController::class, 'getRealtimeStats'])->name('dashboard.realtime-stats');
    Route::get('/password', [PasswordController::class, 'index'])->name('password.index');
    Route::post('/password', [PasswordController::class, 'update'])->name('password.update');
});

Route::get('/laporan/cetak/signed', [LaporanController::class, 'cetakPdfSigned'])
    ->name('laporan.cetak.signed')
    ->middleware('signed');