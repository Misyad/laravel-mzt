@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Detail Log Anggota</h1><div class="description">Riwayat aktivitas anggota</div></div>
</div>

<div class="mzt-card"><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_anggota" width="100%" cellspacing="0">
    <thead><tr><th>Nama</th><th>Subject</th><th>URL</th><th>Method</th><th>User Agent</th><th>waktu</th></tr></thead>
    <tbody></tbody>
  </table>
</div></div></div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

<script>
$(document).ready(function() {
  var table = $('#tabel_anggota').DataTable({
    processing:true,searchable:true,Paginate:true,serverSide:true,pageLength:10,
    ajax:{url:'/tabel-log-user/detail/{{$id}}/data',type:'POST',headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}},
    "columns":[{"data":"nama"},{"data":"subject"},{"data":"url"},{"data":"method"},{"data":"agent"},{"data":"created_at"}],
    "pagingType":"full_numbers"
  });
});
</script>
@endsection