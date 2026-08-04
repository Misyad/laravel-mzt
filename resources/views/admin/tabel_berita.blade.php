@extends('admin.master')
@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<script src="/assets/ckeditor/ckeditor.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Tabel Berita</h1><div class="description">Kelola berita dan pengumuman</div></div>
  <div class="actions"><button type="button" class="mzt-btn mzt-btn-primary" data-toggle="modal" id="btn_tambah"><i class="fas fa-plus"></i> Tambah Berita</button></div>
</div>

<div class="mzt-card"><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_event" width="100%" cellspacing="0">
    <thead><tr><th>Judul</th><th>Slug</th><th>Pembuat</th><th>aksi</th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th>Judul</th><th>Slug</th><th>Pembuat</th><th>aksi</th></tr></tfoot>
  </table>
</div></div></div>

<div class="modal fade bd-example-modal-lg" id="modal_event" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Berita</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <div class="col-md-12 text-center mb-2"><img src="/storage/" id="img_view" alt="" class="img-fluid" srcset=""></div>
      <form id="form_event">
        <div class="mb-3"><label for="judul" class="form-label">Judul <a style="color:red">*</a></label><input type="text" class="form-control" required id="judul" name="judul"><input type="hidden" class="form-control" id="id_berita" name="id_berita"><input type="hidden" class="form-control" id="foto_lama" name="foto_lama"><input type="hidden" class="form-control" id="slug_lama" name="slug_lama"></div>
        <div class="mb-3"><label for="slug" class="form-label">Slug <a style="color:red">*</a></label><input type="text" class="form-control" required id="slug" name="slug"></div>
        <div class="mb-3"><label for="deskripsi" class="form-label">Deskripsi <a style="color:red">*</a></label><textarea class="form-control" id="deskripsi" required maxlength="50" name="deskripsi" rows="3"></textarea></div>
        <div class="mb-3"><label for="foto" class="form-label">Foto <a id="star_edit_2"></a></label><input type="file" class="form-control" id="foto" name="foto"></div>
        <div class="text-right"><a style="color:red">*</a> Wajib diisi</div>
      </div>
      <div class="modal-footer">@csrf<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="/stisla/assets/js/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script>
$(document).ready(function() {
  var aksi_status = true;
  var table = $('#tabel_event').DataTable({
    ajax:{url:'/tabel-berita/data',method:'get',dataSrc:'data'},
    columns:[{data:'judul'},{data:'slug'},{data:'pembuat'}],
    aoColumnDefs:[{targets:3,data:'id',"render":function(data,catatan,row){
      return '<a href="#" id="btn_edit" data-id="'+row.id+'" data-judul="'+row.judul+'" data-slug="'+row.slug+'" data-deskripsi=\''+row.deskripsi+'\' data-foto="'+row.foto+'"><i class="fas fa-edit"></i></a> <a href="#" id="btn_deleted" data-id="'+row.id+'"><i class="fas fa-trash"></i></a>';
    }}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });

  $('#foto').change(function(e){var input=this;if(input.files&&input.files[0]){var reader=new FileReader();reader.onload=function(e){$('#img_view').attr('src',e.target.result);}reader.readAsDataURL(input.files[0]);}});

  $('#tabel_event tbody').on('click','#btn_edit',function(e){
    e.preventDefault();aksi_status=false;
    var id=this.getAttribute('data-id'),judul=this.getAttribute('data-judul'),slug=this.getAttribute('data-slug'),deskripsi=this.getAttribute('data-deskripsi'),foto=this.getAttribute('data-foto');
    $('#id_berita').val(id);$('#judul').val(judul);$('#slug').val(slug);$('#slug_lama').val(slug);$('#foto_lama').val(foto);
    CKEDITOR.instances['deskripsi'].setData(deskripsi);$('#img_view').attr('src','/storage/'+foto);
    $('#star_edit_2').html('');$('#modal_event').modal('show');
  });

  $('#btn_tambah').click(function(e){e.preventDefault();aksi_status=true;$('#star_edit_2').html('<a style="color:red">*</a>');CKEDITOR.instances['deskripsi'].setData('');$('#img_view').attr('src','');$('#modal_event').modal('show');clearData();});

  $('#form_event').submit(function(e){
    e.preventDefault();var data=new FormData(this);var inputcatatan=CKEDITOR.instances['deskripsi'].getData();data.delete('deskripsi');data.append('deskripsi',inputcatatan);
    if(aksi_status){$.ajax({url:"/tabel-berita/store",method:"POST",data:data,processData:false,contentType:false,success:function(data){table.ajax.reload();$('#modal_event').modal('hide');Toast.fire({icon:'success',title:'Simpan Berhasil'});},error:function(data){Toast.fire({icon:'error',title:data['responseJSON']['message']});}});}
    else{$.ajax({url:"/tabel-berita/edit",method:"POST",data:data,processData:false,contentType:false,success:function(data){table.ajax.reload();$('#modal_event').modal('hide');Toast.fire({icon:'success',title:'Simpan Berhasil'});},error:function(data){Toast.fire({icon:'error',title:data['responseJSON']['message']});}});}
  });

  $('#tabel_event tbody').on('click','#btn_deleted',function(e){
    e.preventDefault();var id=this.getAttribute('data-id');
    Swal.fire({title:'Apa kamu yakin ingin hapus data ini?',text:"Data akan hilang setelah dihapus!",icon:'warning',showCancelButton:true,confirmButtonColor:'#3085d6',cancelButtonColor:'#d33',confirmButtonText:'Ya hapus data ini!'}).then((result)=>{
      if(result.isConfirmed){$.ajax({url:"/tabel-berita/delete",method:"POST",headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id':id},success:function(data){table.ajax.reload();Toast.fire({icon:'success',title:'Berhasil hapus data'});},error:function(data,exception){Toast.fire({icon:'error',title:exception});}});}
    });
  });

  CKEDITOR.replace('deskripsi');
  function clearData(){$('#judul').val('');$('#foto').val('');$('#slug').val('');}
});
</script>
@endsection