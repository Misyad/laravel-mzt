@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="/assets/datetime/jquery.datetimepicker.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<script src="/assets/ckeditor/ckeditor.js"></script>
<script src="/assets/datetime/jquery.datetimepicker.full.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Transaksi Event</h1><div class="description">{{$data->judul_event}}</div></div>
</div>

<div class="mzt-card mzt-mb-4"><div class="mzt-card-body">
  <div class="mzt-text-center mzt-mb-4"><h4>{{$data->judul_event}}</h4></div>
  @if ($data->banner)
    <div class="mzt-text-center mzt-mb-4"><img alt="image" src="/storage/{{$data->banner}}" class="img-fluid" style="border-radius:12px;max-height:300px;"></div>
  @endif
  @php echo $data->deskripsi; @endphp
</div></div>

<div class="mzt-card"><div class="mzt-card-header"><h4>Peserta</h4></div><div class="mzt-card-body">
  <div class="mzt-text-right mzt-mb-4">
    <button type="button" class="mzt-btn mzt-btn-primary" onclick="tambahTransaksiAnggota()">Tambah Transaksi Anggota Terdaftar</button>
    <button type="button" class="mzt-btn mzt-btn-primary" onclick="tambahTransaksi()">Tambah Transaksi</button>
  </div>
  <div class="mzt-table-wrap">
    <table class="table table-bordered" id="tabel_event" width="100%" cellspacing="0">
      <thead><tr><th class="text-center">No</th><th class="text-center">ID Anggota</th><th class="text-center">Nama</th><th class="text-center">Id Transaksi</th><th class="text-center">Pembayaran</th><th class="text-center">Status</th><th class="text-center">Waktu</th><th class="text-center">aksi</th></tr></thead>
      <tbody>
        @php $no = 1; @endphp
        @foreach ($transaksi as $item)
        <tr>
          <td class="text-center">{{$no++}}</td>
          <td>{{$item->id_anggota}}</td>
          <td>{{$item->name}}</td>
          <td>{{$item->order_id}}</td>
          <td>Rp. {!! ($item->gross_amount)?$item->gross_amount:'-' !!}</td>
          <td class="text-center">@php
            switch ($item->transaction_status) {
              case 'settlement': echo '<span class="mzt-badge mzt-badge-success">Pembayaran Berhasil</span>'; break;
              case 'pending': echo '<span class="mzt-badge mzt-badge-primary">Pembayaran Pending</span>'; break;
              case 'deny': echo '<span class="mzt-badge mzt-badge-danger">Pembayaran Ditolak</span>'; break;
              case 'cancel': echo '<span class="mzt-badge mzt-badge-warning">Pembayaran Dibatalkan</span>'; break;
              case 'expire': echo '<span class="mzt-badge mzt-badge-warning">Pembayaran Expired</span>'; break;
              case 'manual': echo '<span class="mzt-badge mzt-badge-secondary">Manual Verifikasi</span>'; break;
              default: echo '-'; break;
            }
          @endphp</td>
          <td>{{$item->created_at}}</td>
          <td class="text-center">
            @if($item->transaction_status == 'manual')
              <button class="mzt-btn mzt-btn-sm mzt-btn-primary" onclick="verifikasiPendaftarModal('{{$item->id_anggota}}')">Verifikasi</button>
            @endif
            @if($item->transaction_status == 'settlement' || $item->transaction_status == 'manual')
              <a href="{{ route('id-card.print', ['id_event' => $id_event, 'id_transaction' => $item->order_id]) }}" class="mzt-btn mzt-btn-sm mzt-btn-outline"><i class="far fa-id-card"></i> ID Card</a>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div></div>

{{-- Modal Tambah Transaksi --}}
<div class="modal fade" id="modal_bayar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tambah Transaksi</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <form id="formDaftar">
        @csrf
        <div class="mb-3"><label class="form-label">ID Anggota</label><input type="text" class="form-control" name="id_anggota" required></div>
        <div class="mb-3"><label class="form-label">Nama</label><input type="text" class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
        <div class="mb-3"><label class="form-label">No HP</label><input type="text" class="form-control" name="no_hp"></div>
        <div class="mb-3"><label class="form-label">Jumlah Bayar</label><input type="text" class="form-control" id="harga_transaksi" name="harga_transaksi" required></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>

{{-- Modal Verifikasi --}}
<div class="modal fade" id="modalverifikasi" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Verifikasi Pembayaran</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <form id="formVerifikasi">
        @csrf
        <div class="mb-3"><label class="form-label">ID Anggota</label><input type="text" class="form-control" id="id_anggota" name="id_anggota" readonly></div>
        <div class="mb-3"><label class="form-label">Status Verifikasi</label><select class="form-control" name="status"><option value="settlement">Berhasil</option><option value="deny">Ditolak</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Verifikasi</button></form></div>
  </div></div>
</div>

{{-- Modal Tambah Transaksi Anggota Terdaftar --}}
<div class="modal fade" id="modal_transaksi_admin" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Tambah Transaksi Anggota</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <form id="formTransaksiAdmin">
        @csrf
        <div class="mb-3"><label class="form-label">Pilih Anggota</label><select class="form-control select2" name="id_anggota" style="width:100%" required>
          @foreach($anggota as $a)
          <option value="{{$a->id_anggota}}">{{$a->nama}} ({{$a->id_anggota}})</option>
          @endforeach
        </select></div>
        <div class="mb-3"><label class="form-label">Jumlah Bayar</label><input type="text" class="form-control" id="harga_admin" name="harga_transaksi" required></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>

<script src="/stisla/assets/js/moment.min.js"></script>

<script>
$(document).ready(function() {
  $('.select2').select2();

  $('#harga_transaksi').on('keyup', function(){
    var numericValue = this.value.replace(/[^0-9]/g, '');
    $(this).val(formatRupiah(numericValue, 'Rp. '));
  });

  $('#harga_admin').on('keyup', function(){
    var numericValue = this.value.replace(/[^0-9]/g, '');
    $(this).val(formatRupiah(numericValue, 'Rp. '));
  });

  function formatRupiah(angka, prefix) {
    if (typeof angka === 'string') { rupiah = angka; }
    else if (typeof angka === 'number') { rupiah = angka.toString(); }
    else { rupiah = '0'; }
    var split = rupiah.split(',');
    var sisa = split[0].length % 3;
    rupiah = split[0].substr(0, sisa);
    var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) { separator = sisa ? '.' : ''; rupiah += separator + ribuan.join('.'); }
    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    if (prefix == undefined) { return rupiah; } else { return rupiah ? prefix + rupiah : prefix; }
  }

  function unformatRupiah(rupiah) { return parseInt(rupiah.replace(/[^0-9]/g, ''), 10); }

  function tambahTransaksi() { $('#modal_bayar').modal('show'); }

  $('#formDaftar').on('submit', function(e){
    e.preventDefault();var data=new FormData(this);data.append('id_event','{{$id_event}}');
    $.ajax({url:"/tabel-event-transaksi/simpan",method:"POST",data:data,headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},processData:false,contentType:false,
      success:function(response){if(response.success){Swal.fire('berhasil !!!','Pendaftaran berhasil',"success");location.reload();}else{Swal.fire(response.message,response.data,"error");}},
      error:function(response){Swal.fire('Pendaftaran Gagal!',response.responseJSON.data,"error");}
    });
  });

  function verifikasiPendaftarModal(id_anggota) {
    $('#id_anggota').val(id_anggota);$('#modalverifikasi').modal('show');
  }

  $('#formVerifikasi').on('submit', function(e){
    e.preventDefault();var data=new FormData(this);data.append('id_event','{{$id_event}}');
    $.ajax({url:"/tabel-event-transaksi/verifikasi",method:"POST",data:data,headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},processData:false,contentType:false,
      success:function(response){if(response.success){Swal.fire('berhasil !!!','Verifikasi berhasil',"success");location.reload();}else{Swal.fire(response.message,response.data,"error");}},
      error:function(response){Swal.fire('Pendaftaran Gagal!',response.responseJSON.message,"error");}
    });
  });

  $('#formTransaksiAdmin').on('submit', function(e){
    e.preventDefault();var data=new FormData(this);data.append('id_event','{{$id_event}}');
    $.ajax({url:"/tabel-event-transaksi/tambah-transasi-anggota",method:"POST",data:data,headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},processData:false,contentType:false,
      success:function(response){if(response.success){Swal.fire('berhasil !!!','Tambah berhasil',"success");location.reload();}else{Swal.fire(response.message,response.data,"error");}},
      error:function(response){Swal.fire('Pendaftaran Gagal!',response.responseJSON.message,"error");}
    });
  });

  function tambahTransaksiAnggota() { $('#modal_transaksi_admin').modal('show'); }
});
</script>
@endsection