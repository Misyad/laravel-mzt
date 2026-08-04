@extends('admin.master')
@section('konten')
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div><h1>ID Card</h1><div class="description">Kelola template ID Card</div></div>
  <div class="actions">
    <button type="button" class="mzt-btn mzt-btn-primary" data-toggle="modal" data-target="#modal_card">
      <i class="fas fa-plus"></i> Tambah ID Card
    </button>
  </div>
</div>

<div class="mzt-card"><div class="mzt-card-body">
  <div class="mzt-grid mzt-grid-4">
    @foreach($data as $template)
    <div class="mzt-card" style="border:2px solid transparent;transition:border-color 0.2s;">
      <div style="padding:8px;">
        <label for="radio_{{$template->id}}" style="cursor:pointer;margin:0;">
          <input type="radio" name="template_id" id="radio_{{$template->id}}" value="{{$template->id}}" @if ($template->status === 'ACTIVE') checked @endif style="margin-right:6px;">
          <img style="width:100%;border-radius:8px;" src="{{ asset('storage/' . $template->path) }}" alt="{{$template->nama_gambar}}">
        </label>
      </div>
      <div style="padding:8px;display:flex;gap:6px;">
        <a href="/id-card/{{$template->id}}" style="flex:1;"><button class="mzt-btn mzt-btn-sm mzt-btn-outline" style="width:100%;">Ubah</button></a>
        <button class="mzt-btn mzt-btn-sm mzt-btn-danger" style="flex:1;">Hapus</button>
      </div>
    </div>
    @endforeach
  </div>
</div></div>

<div class="modal fade bd-example-modal-lg" id="modal_card" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">ID Card</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <form method="POST" action="{{ route('id-card.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3"><label for="foto" class="form-label">Foto <span style="color:red">*</span></label><input type="file" class="form-control" id="foto" name="foto"></div>
        <div class="text-right"><span style="color:red">*</span> Wajib diisi</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>
@endsection