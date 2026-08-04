<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\home\HomeViews;
use App\Http\Controllers\admin\Dashboard;
use App\Http\Controllers\admin\Login;
use App\Http\Controllers\admin\C_Anggota;
use App\Http\Controllers\admin\C_profil;
use App\Http\Controllers\admin\C_Event;
use App\Http\Controllers\admin\C_event_detail;
use App\Http\Controllers\admin\C_prisensi;
use App\Http\Controllers\admin\C_Berita;
use App\Http\Controllers\admin\C_Tampilan;
use App\Http\Controllers\admin\C_Aktivitas_log;
use App\Http\Controllers\admin\C_transaksi;
use App\Http\Controllers\admin\C_ID_Card;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/',[ HomeViews::class, 'index']);
Route::get('/tentang-mzt',[ HomeViews::class, 'tentagMzt']);
Route::get('/berita',[ HomeViews::class, 'berita']);
Route::get('/pengumunan',[ HomeViews::class, 'event']);
Route::post('/load-berita',[ HomeViews::class, 'loadData']);
Route::get('/berita/{id}',[ HomeViews::class, 'bertiaDetail']);
Route::get('/pengumunan/{id}',[ HomeViews::class, 'eventDetail']);
Route::post('/pengumunan/infak/pembayaran',[ HomeViews::class, 'pembayaranInfak']);
Route::post('/transaksi/pembayaran',[ HomeViews::class, 'simpanPembayaran']);


Route::middleware(['guest', 'revalidate'])->group(function () {
    Route::get('/login',[ Login::class, 'viewLogin'])->name('login');
    Route::post('/login-aksi',[ Login::class, 'action_login']);
});
Route::middleware(['checkrole:dashboard', 'revalidate'])->group(function () {
    Route::get('/dashboard',[ Dashboard::class, 'index']);
    Route::get('/dashboard/get-calender',[ Dashboard::class, 'getCalender']);
});

Route::middleware(['checkrole:anggota', 'revalidate'])->group(function () {
    Route::get('/tabel-anggota',[ C_Anggota::class, 'tabelAnggota']);
    Route::post('/tabel-anggota/store',[ C_Anggota::class, 'storeData']);
    Route::get('/tabel-anggota/data',[ C_Anggota::class, 'getData']);
    Route::post('/tabel-anggota/data-hak-akses',[ C_Anggota::class, 'getDataHakakses']);
    Route::post('/tabel-anggota/edit',[ C_Anggota::class, 'editData']);
    Route::post('/tabel-anggota/hapus',[ C_Anggota::class, 'deleteData']);
    Route::get('/tabel-anggota/kta/{id}',[ C_Anggota::class, 'exportPdf']);
});

Route::middleware(['checkrole:profil', 'revalidate'])->group(function () {
    Route::get('/profil',[ C_profil::class, 'index']);
    Route::post('/profil/edit',[ C_profil::class, 'saveData']);

});
Route::middleware(['checkrole:event', 'revalidate'])->group(function () {
    Route::get('/tabel-event',[ C_Event::class, 'index']);
    Route::post('/tabel-event/store',[ C_Event::class, 'storeData']);
    Route::get('/tabel-event/data',[ C_Event::class, 'getData']);
    Route::post('/tabel-event/edit',[ C_Event::class, 'editData']);
    Route::post('/tabel-event/hapus',[ C_Event::class, 'delete']);

    Route::get('/tabel-event/detail/{id}',[ C_event_detail::class, 'index']);
    Route::post('/tabel-event/detail/data',[ C_event_detail::class, 'getData']);
    Route::post('/tabel-event/detail/save',[ C_event_detail::class, 'saveData']);

    Route::get('/tabel-event-transaksi',[ C_transaksi::class, 'index']);
    Route::get('/tabel-event-transaksi/transaksi',[ C_transaksi::class, 'tabelTransaksi']);
    Route::post('/tabel-event-transaksi/simpan',[ C_transaksi::class, 'tambahTransaksiAdmin']);
    Route::post('/tabel-event-transaksi/verifikasi',[ C_transaksi::class, 'verifikasiPendaftar']);
    Route::post('/tabel-event-transaksi/tambah-transasi-anggota',[ C_transaksi::class, 'tambahTransaksiAnggota']);

    Route::get('/tabel-event/detail/{id}/{id2}/prisensi',[ C_prisensi::class, 'index']);
    Route::post('/data-user-prisensi',[ C_prisensi::class, 'getDataUser']);
    Route::post('/data-user-prisensi/send-data',[ C_prisensi::class, 'sendData']);
    Route::post('/data-user-prisensi/get-data-tabel',[ C_prisensi::class, 'getDataTabel']);

});
Route::middleware(['checkrole:prisensi', 'revalidate'])->group(function () {
    Route::get('/tabel-prisensi',[ C_prisensi::class, 'tabel_prisensi']);
    Route::get('/tabel-prisensi/data',[ C_Event::class, 'getData']);
    Route::get('/tabel-prisensi/detail/{id}',[ C_event_detail::class, 'index']);
    Route::post('/tabel-prisensi/detail/data',[ C_event_detail::class, 'getData']);

    Route::get('/tabel-prisensi/detail/{id}/{id2}/prisensi',[ C_prisensi::class, 'index']);
    Route::post('/data-user-prisensi-anggota',[ C_prisensi::class, 'getDataUser']);
    Route::post('/data-user-prisensi-anggota/send-data',[ C_prisensi::class, 'sendData']);
    Route::post('/data-user-prisensi-anggota/get-data-tabel',[ C_prisensi::class, 'getDataTabel']);


});
Route::middleware(['checkrole:berita', 'revalidate'])->group(function () {
    Route::get('/tabel-berita',[ C_Berita::class, 'index']);
    Route::post('/tabel-berita/store',[ C_Berita::class, 'storeData']);
    Route::post('/tabel-berita/edit',[ C_Berita::class, 'editData']);
    Route::post('/tabel-berita/delete',[ C_Berita::class, 'deleteData']);
    Route::get('/tabel-berita/data',[ C_Berita::class, 'getData']);
});
Route::middleware(['checkrole:tampilan', 'revalidate'])->group(function () {
    Route::get('/edit-info-pesantren',[ C_Tampilan::class, 'tentanPondok']);
    Route::get('/edit-info-mzt',[ C_Tampilan::class, 'tentanMzt']);
    Route::get('/edit-carosel',[ C_Tampilan::class, 'carosel']);
    // Route::post('/edit-info-pesantren/simpan',[ C_Tampilan::class, 'simpanDataPesantren']);
    Route::post('/edit-info-pesantren/simpan',[ C_Tampilan::class, 'simpanDataPesantren2']);
    Route::post('/edit-info-mzt/simpan',[ C_Tampilan::class, 'simpanDataMzt']);
    Route::post('/edit-carosel/simpan',[ C_Tampilan::class, 'simpanCarosel']);
    
});
Route::middleware(['checkrole:aktivitas_user', 'revalidate'])->group(function () {
    Route::get('/tabel-log-user',[ C_Aktivitas_log::class, 'index']);
    Route::get('/tabel-log-user/data',[ C_Anggota::class, 'getData']);
    Route::get('/tabel-log-user/detail/{id}',[ C_Aktivitas_log::class, 'aktivitas']);
    Route::post('/tabel-log-user/detail/{id}/data',[ C_Aktivitas_log::class, 'dataAktivitasLog']);

});
Route::middleware(['checkrole:id_card', 'revalidate'])->group(function () {
    Route::get('/id-card',[ C_ID_Card::class, 'index']);
    Route::post('/id-card/store', [C_ID_Card::class, 'store'])->name('id-card.store');
    Route::get('/id-card/{id}', [C_ID_Card::class, 'setComponent'])->name('id-card.set-component');
    Route::post('/id-card/store/component', [C_ID_Card::class, 'setLayoutComponent'])->name('id-card.store.component');
    Route::get('/tabel-event-transaksi/transaksi/{id_event}/{id_transaction}', [C_ID_Card::class, 'printCard'])->name('id-card.print');

    // Route::post('/tabel-berita/edit',[ C_Berita::class, 'editData']);
    // Route::post('/tabel-berita/delete',[ C_Berita::class, 'deleteData']);
    // Route::get('/tabel-berita/data',[ C_Berita::class, 'getData']);
});


Route::get('/logout', [Login::class, 'logout']);