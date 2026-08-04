@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Log Anggota</h1><div class="description">Daftar anggota dan aktivitas log</div></div>
</div>

<div class="mzt-card"><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_anggota" width="100%" cellspacing="0">
    <thead><tr><th>Name</th><th>ID anggota</th><th>Alamat</th><th>Niqobah</th><th>Tahun Masuk</th><th>aksi</th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th>Name</th><th>ID anggota</th><th>Alamat</th><th>Niqobah</th><th>Tahun Masuk</th><th>aksi</th></tr></tfoot>
  </table>
</div></div></div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

<script>
$(document).ready(function() {
  var table = $('#tabel_anggota').DataTable({
    ajax:{url:'/tabel-log-user/data',method:'get',dataSrc:'data'},
    columns:[{data:'nama'},{data:'id_anggota'},{data:'alamat'},{data:'niqobah'},{data:function(data){return moment(data.tahun_masuk).format('DD-MM-YYYY');}}],
    aoColumnDefs:[{targets:5,data:'id',"render":function(data,catatan,row){
      return '<a href="/tabel-log-user/detail/'+row.id_users+'"><i class="far fa-arrow-alt-circle-right"></i></a>';
    }}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });
});
</script>
@endsection