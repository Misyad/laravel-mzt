@extends('admin.master')
@section('konten')
<meta name="csrf-token" content="{{ csrf_token() }}" />

<style>
  #div_niqobah { position: absolute; z-index: 9; background-color: transparent; text-align: center; border: 1px solid #cccccc; }
  #div_name { position: absolute; z-index: 9; background-color: transparent; text-align: center; border: 1px solid #cccccc; }
  #div_photo { position: absolute; z-index: 9; background-color: transparent; text-align: center; border: 1px solid #cccccc; }
  #mydivphoto { width: 30mm; height: 40mm; padding: 0 10px; cursor: move; z-index: 10; background-color: transparent; color: #000; }
  #mydivheader { padding: 0 10px; cursor: move; z-index: 10; background-color: transparent; color: #000; }
</style>

<div class="mzt-page-header">
  <div><h1>Layout ID Card</h1><div class="description">Atur posisi komponen pada template ID Card</div></div>
</div>

<div class="mzt-card"><div class="mzt-card-body">
  <input type="hidden" name="id" id="id" value="<?=$id?>">

  <div style="height:122mm;width:94mm;position:relative;margin:0 auto;border:1px solid var(--mzt-border);border-radius:8px;overflow:hidden;">
    <img style="height:122mm;width:94mm;" src="{{ asset('storage/' . $data->path) }}" alt="{{ $data->nama_gambar }}">
    <div id="div_name" @if ($name) style="top: {{$name->position_x}}; left: {{$name->position_y}};" @endif>
      <div id="mydivheader">Nama anda...</div>
    </div>
    <div id="div_niqobah" @if ($niqobah) style="top: {{$niqobah->position_x}}; left: {{$niqobah->position_y}};" @endif>
      <div id="mydivheader">Nama niqobah...</div>
    </div>
    <div id="div_photo" @if ($photo) style="top: {{$photo->position_x}}; left: {{$photo->position_y}};" @endif>
      <div id="mydivphoto"></div>
    </div>
  </div>

  <div class="mzt-text-center mzt-mt-4">
    <button class="mzt-btn mzt-btn-primary mzt-btn-lg" onclick="onSave()">
      <i class="fas fa-save"></i> Simpan
    </button>
  </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  dragElement(document.getElementById("div_name"));
  dragElement(document.getElementById("div_niqobah"));
  dragElement(document.getElementById("div_photo"));

  function dragElement(elmnt) {
    var pos1=0,pos2=0,pos3=0,pos4=0;
    if(document.getElementById(elmnt.id+"header")){document.getElementById(elmnt.id+"header").onmousedown=dragMouseDown;}
    else{elmnt.onmousedown=dragMouseDown;}
    function dragMouseDown(e){e=e||window.event;e.preventDefault();pos3=e.clientX;pos4=e.clientY;document.onmouseup=closeDragElement;document.onmousemove=elementDrag;}
    function elementDrag(e){e=e||window.event;e.preventDefault();pos1=pos3-e.clientX;pos2=pos4-e.clientY;pos3=e.clientX;pos4=e.clientY;elmnt.style.top=(elmnt.offsetTop-pos2)+"px";elmnt.style.left=(elmnt.offsetLeft-pos1)+"px";}
    function closeDragElement(){document.onmouseup=null;document.onmousemove=null;}
  }

  function onSave() {
    var id=document.getElementById('id').value;
    var elementPhoto=document.getElementById("div_photo");var photoX=elementPhoto.style.top;var photoY=elementPhoto.style.left;
    var elementNiqobah=document.getElementById("div_niqobah");var niqobahX=elementNiqobah.style.top;var niqobahY=elementNiqobah.style.left;
    var elementName=document.getElementById("div_name");var nameX=elementName.style.top;var nameY=elementName.style.left;
    $.ajax({type:"POST",url:"{{ route('id-card.store.component') }}",data:{id:id,photoX:photoX,photoY:photoY,niqobahX:niqobahX,niqobahY:niqobahY,nameX:nameX,nameY:nameY,_token:"{{ csrf_token() }}"},
      success:function(data){Swal.fire({icon:'success',title:'Berhasil',text:'Data layout berhasil disimpan'});},
      error:function(xhr,status,error){Swal.fire({icon:'error',title:'Gagal',text:'Data layout gagal disimpan'});console.error(xhr.responseText);}
    });
  }
</script>
@endsection