@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Presensi</h1><div class="description">Pilih event untuk input presensi</div></div>
</div>

<div class="mzt-card"><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_event" width="100%" cellspacing="0">
    <thead><tr><th>Judul</th><th>Lokasi</th><th>Slug</th><th>Tanggal</th><th>aksi</th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th>Judul</th><th>Lokasi</th><th>Slug</th><th>Tanggal</th><th>aksi</th></tr></tfoot>
  </table>
</div></div></div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="/stisla/assets/js/moment.min.js"></script>

<script>
$(document).ready(function() {
  var table = $('#tabel_event').DataTable({
    ajax:{url:'/tabel-prisensi/data',method:'get',dataSrc:'data'},
    columns:[{data:'judul_event'},{data:'lokasi'},{data:'slug'},{data:'tanggal'}],
    aoColumnDefs:[{targets:4,data:'id',"render":function(data,catatan,row){
      return '<a href="/tabel-prisensi/detail/'+row.id+'"><i class="far fa-arrow-alt-circle-right"></i></a>';
    }}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });
});
</script>
@endsection