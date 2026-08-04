@extends('admin.master')

@section('konten')
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div>
    <h1>Profil</h1>
    <div class="description">{{auth()->user()->name}}</div>
  </div>
</div>

<div class="mzt-card">
  <div class="mzt-card-body">
    <div class="mzt-text-center mzt-mb-4">
      <img alt="image" src="/storage/{{$foto_profil}}" class="mzt-avatar" style="width:96px;height:96px;font-size:32px;border-radius:50%;">
    </div>
    <form action="/profil/edit" method="post" enctype="multipart/form-data" style="max-width:600px;margin:0 auto;">
      @csrf
      <div class="mb-3">
        <label for="nama" class="form-label">Nama <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="text" class="form-control" value="{{$profil->nama}}" required id="nama" name="nama">
        <input type="hidden" class="form-control" id="id_users" value="{{$profil->id_users}}" name="id_users">
        <input type="hidden" class="form-control" id="barcode" value="{{$profil->barcode}}" name="barcode">
        <input type="hidden" class="form-control" id="foto_lama" value="{{$profil->foto}}" name="foto_lama">
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" value="{{$profil->email}}" id="email" name="email">
      </div>
      <div class="mb-3">
        <label for="no_hp" class="form-label">No telpon <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="number" class="form-control" required value="{{$profil->no_hp}}" id="no_hp" name="no_hp">
      </div>
      <div class="mb-3">
        <label for="alamat" class="form-label">Alamat <span style="color:var(--mzt-destructive)">*</span></label>
        <textarea class="form-control" id="alamat" required maxlength="50" name="alamat" rows="3">{{$profil->alamat}}</textarea>
      </div>
      <div class="mb-3">
        <label for="niqobah" class="form-label">Niqobah <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="text" class="form-control" required id="niqobah" value="{{$profil->niqobah}}" maxlength="15" name="niqobah">
      </div>
      <div class="mb-3">
        <label for="pekerjaan" class="form-label">Pekerjaan <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="text" class="form-control" required value="{{$profil->pekerjaan}}" id="pekerjaan" maxlength="15" name="pekerjaan">
      </div>
      <div class="mb-3">
        <label for="tanggal_lahir" class="form-label">Tanggal lahir <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="date" class="form-control" required id="tanggal_lahir" value="{{$profil->tanggal_lahir}}" name="tanggal_lahir">
      </div>
      <div class="mb-3">
        <label for="tahun_masuk" class="form-label">Tahun masuk <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="date" class="form-control" required id="tahun_masuk" value="{{$profil->tahun_masuk}}" name="tahun_masuk">
      </div>
      <div class="mb-3">
        <label for="tahun_keluar" class="form-label">Tahun keluar <span style="color:var(--mzt-destructive)">*</span></label>
        <input type="date" class="form-control" required id="tahun_keluar" value="{{$profil->tahun_keluar}}" name="tahun_keluar">
      </div>
      <div class="mb-3">
        <label for="foto" class="form-label">Foto</label>
        <input type="file" class="form-control" id="foto" name="foto">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password <span id="star_edit_2"></span></label>
        <input type="password" class="form-control" id="password" name="password">
      </div>
      <div class="mzt-text-right mzt-mt-4">
        <button type="submit" class="mzt-btn mzt-btn-primary mzt-btn-lg">Simpan</button>
      </div>
    </form>
  </div>
</div>

@if (session()->has('error'))
<script>Toast.fire({icon:'error',title:'Gagal Update'});</script>
@endif
@if (session()->has('error2'))
<script>Toast.fire({icon:'error',title:'Gagal Simpan Hubungi Admin'});</script>
@endif
@if (session()->has('sukses'))
<script>Toast.fire({icon:'success',title:'Berhasil Simpan Data'});</script>
@endif
@endsection