@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="/assets/datetime/jquery.datetimepicker.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<script src="/assets/ckeditor/ckeditor.js"></script>
<script src="/assets/datetime/jquery.datetimepicker.full.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>

<div class="mzt-page-header">
  <div><h1>Presensi</h1><div class="description">Scan barcode kehadiran</div></div>
</div>

@if(isset($id_event) && isset($id_tanggal))
<div class="mzt-card mzt-mb-4"><div class="mzt-card-header"><h4>Scan Barcode</h4></div><div class="mzt-card-body">
  <div class="mzt-flex mzt-gap-2" style="align-items:flex-end;">
    <div style="flex:1"><input type='text' class="form-control" id="input_scan"></div>
    <button type="button" id="btn_clear_text" class="mzt-btn mzt-btn-primary">Clear Text</button>
  </div>
</div></div>

<div class="mzt-card"><div class="mzt-card-header"><h4>Presensi</h4></div><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_event" width="100%" cellspacing="0">
    <thead><tr><th>Id Anggota</th><th>Nama</th><th>Tanggal Hadir</th><th>Jam Hadir</th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th>Id Anggota</th><th>Nama</th><th>Tanggal Hadir</th><th>Jam Hadir</th></tr></tfoot>
  </table>
</div></div></div>

<div class="modal fade bd-example-modal-sm" id="modal_event" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Anggota</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <div class="col-md-12 text-center mb-2"><img src="/storage/" id="img_view" alt="" class="img-fluid" srcset=""></div>
      <div class="col-md-12 text-center"><a id="nama_atas" style="font-size:18px;"></a></div>
      <div class="col-md-12 text-center mb-3 mt-3"><button type="button" class="btn btn-primary" id="button_detail">Detail</button></div>
      <form id="form_prisensi" style="display: none;">
        <div class="mb-3"><label for="id_anggota" class="form-label">ID Anggota</label><input type="text" class="form-control" disabled required id="id_anggota" name="id_anggota"></div>
        <div class="mb-3"><label for="nama" class="form-label">Nama</label><input type="text" class="form-control" disabled id="nama_modal" name="nama"></div>
        <div class="mb-3"><label for="alamat" class="form-label">Alamat</label><input type="text" class="form-control" disabled id="alamat_modal" name="alamat"></div>
      </div>
      <div class="modal-footer">@csrf<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="/stisla/assets/js/moment.min.js"></script>

<script>
$(document).ready(function() {
  var interval;
  $('#btn_clear_text').click(function(e){e.preventDefault();$('#input_scan').val("").focus();});
  $('#input_scan').focus();

  $('#input_scan').on('keyup',function(e){
    if(e.keyCode===13){
      var id_anggota=$('#input_scan').val();
      $.ajax({url:"/data-user-prisensi-anggota",method:"POST",headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id_anggota':id_anggota},
        success:function(data){
          if(data.success){
            $('#id_anggota').val(data.data.id_anggota);
            $('#nama_modal').val(data.data.nama);
            $('#alamat_modal').val(data.data.alamat);
            $('#nama_atas').text(data.data.nama);
            if(data.data.foto){$('#img_view').attr('src','/storage/'+data.data.foto);}
            $('#modal_event').modal('show');
            interval = window.setInterval(function(){$('#modal_event').modal('hide');$('#form_prisensi').submit();},5000);
          }
        },
        error:function(data,exception){console.log(data);Toast.fire({icon:'error',title:exception});}
      });
    }
  });

  $('#form_prisensi').submit(function(e){
    e.preventDefault();var data=new FormData(this);var id_event={{$id_event}};var id_tanggal={{$id_tanggal}};
    data.append('id_event',id_event);data.append('id_tanggal',id_tanggal);
    $.ajax({url:"/data-user-prisensi-anggota/send-data",method:"POST",data:data,processData:false,contentType:false,
      success:function(data){table.ajax.reload();$('#modal_event').modal('hide');$('#input_scan').val("").focus();Toast.fire({icon:'success',title:'Prisensi Berhasil'});window.clearInterval(interval);},
      error:function(data){Toast.fire({icon:'error',title:data['responseJSON']['message']});$('#input_scan').val("").focus();window.clearInterval(interval);}
    });
  });

  var table = $('#tabel_event').DataTable({
    ajax:{url:'/data-user-prisensi-anggota/get-data-tabel',method:'post',dataSrc:'data',headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id_event':{{$id_event}},'id_tanggal':{{$id_tanggal}}}},
    columns:[{data:'id_anggota'},{data:'nama'},{data:function(data){return moment(data.tanggal_kehadiran).format('DD-MM-YYYY');}},{data:function(data){return moment(data.jam_kehadiran).format('HH:mm:ss');}}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });
});
</script>
@else
<div class="mzt-card"><div class="mzt-card-body">
  <p class="mzt-text-muted">Tidak ada data event. Silakan pilih event terlebih dahulu.</p>
</div></div>
@endif
@endsection