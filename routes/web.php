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

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::middleware('admin.auth')->group(function () {
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
    Route::get('/dashboard/realtime-stats', [DashboardController::class, 'getRealtimeStats'])->name('dashboard.realtime-stats');
    Route::get('/password', [PasswordController::class, 'index'])->name('password.index');
});