@extends('admin.master')
@section('konten')
<script src="/assets/ckeditor/ckeditor.js"></script>
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>Info Maziltu Tholiban</h1><div class="description">Tentang &middot; Info Maziltu Tholiban</div></div>
</div>

<div class="mzt-card"><div class="mzt-card-body" style="max-width:700px;margin:0 auto;">
  <div class="mzt-text-center mzt-mb-4">
    <img alt="image" id="img_view" src="/storage/{{$data->foto}}" class="img-fluid" style="border-radius:12px;max-height:300px;object-fit:cover;">
  </div>
  <form id="form_info_pesantren" method="post" enctype="multipart/form-data">
    @csrf
    <div class="mb-3"><label for="judul" class="form-label">Judul <a style="color:red">*</a></label><input type="text" class="form-control" required id="judul" value="{{$data->judul}}" name="judul"><input type="hidden" class="form-control" id="foto_lama" value="{{$data->foto}}" name="foto_lama"><input type="hidden" class="form-control" id="id" value="{{$data->id}}" name="id"></div>
    <div class="mb-3"><label for="deskripsi" class="form-label">Deskripsi <a style="color:red">*</a></label><textarea class="form-control" id="deskripsi" required maxlength="50" name="deskripsi" rows="3">{{$data->deskripsi}}</textarea></div>
    <div class="mb-3"><label for="alamat" class="form-label">Alamat <a style="color:red">*</a></label><input type="text" class="form-control" id="alamat" value="{{$data->alamat}}" name="alamat"></div>
    <div class="mb-3"><label for="no_tlp" class="form-label">Nomor telpon <a style="color:red">*</a></label><input type="text" class="form-control" id="no_tlp" value="{{$data->telpon}}" name="no_tlp"></div>
    <div class="mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" value="{{$data->email}}" name="email"></div>
    <div class="mb-3"><label for="foto" class="form-label">Foto <a style="color:red">*</a></label><input type="file" class="form-control" id="foto" name="foto"></div>
    <div class="mzt-text-right mzt-mt-4"><button type="submit" class="mzt-btn mzt-btn-primary mzt-btn-lg">Simpan</button></div>
  </form>
</div></div>

<script>
$(document).ready(function() {
  CKEDITOR.replace('deskripsi');
  $('#form_info_pesantren').submit(function(e){
    e.preventDefault();var data=new FormData(this);var inputcatatan=CKEDITOR.instances['deskripsi'].getData();data.delete('deskripsi');data.append('deskripsi',inputcatatan);
    $.ajax({url:"/edit-info-mzt/simpan",method:"POST",data:data,processData:false,contentType:false,
      success:function(data){if(data['foto']){$('#img_view').attr('src','/storage/'+data.foto);$('#foto_lama').val(data.foto);}Toast.fire({icon:'success',title:'Simpan Berhasil'});},
      error:function(data){Toast.fire({icon:'error',title:data['responseJSON']['message']});}
    });
  });
});
</script>
@endsection