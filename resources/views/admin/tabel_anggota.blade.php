@extends('admin.master')

@section('konten')
<link href="/assets/vendor_datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
<link href="/assets/datatables/buttons.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div>
    <h1>Anggota</h1>
    <div class="description">Tabel Anggota</div>
  </div>
  <div class="actions">
    <button type="button" class="mzt-btn mzt-btn-primary" data-toggle="modal" id="btn_tambah">
      <i class="fas fa-plus"></i> Tambah Anggota
    </button>
  </div>
</div>

<div class="mzt-card">
  <div class="mzt-card-body">
    <div class="mzt-table-wrap">
      <table class="table table-bordered" id="tabel_anggota" width="100%" cellspacing="0">
        <thead>
          <tr><th>No</th><th>Name</th><th>ID anggota</th><th>Alamat</th><th>Niqobah</th><th>Tahun Masuk</th><th>aksi</th></tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr><th>No</th><th>Name</th><th>ID anggota</th><th>Alamat</th><th>Niqobah</th><th>Tahun Masuk</th><th>aksi</th></tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

{{-- modal input --}}
<div class="modal fade bd-example-modal-lg" id="modal_anggota" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Anggota</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="col-md-12 text-center mb-2">
          <img src="/storage/" id="img_view" alt="" class="img-fluid" srcset="">
        </div>
        <form id="form_anggota">
          <div class="mb-3">
            <label for="nama" class="form-label">Nama <a style="color:red">*</a></label>
            <input type="text" class="form-control" required id="nama" name="nama">
            <input type="hidden" class="form-control" id="id_users" name="id_users">
            <input type="hidden" class="form-control" id="barcode" name="barcode">
            <input type="hidden" class="form-control" id="foto_lama" name="foto_lama">
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
          </div>
          <div class="mb-3">
            <label for="no_hp" class="form-label">No telpon</label>
            <input type="number" class="form-control" id="no_hp" name="no_hp">
          </div>
          <div class="mb-3">
            <label for="alamat" class="form-label">Alamat <a style="color:red">*</a></label>
            <textarea class="form-control" id="alamat" required maxlength="50" name="alamat" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label for="niqobah" class="form-label">Niqobah<a style="color:red">*</a></label>
            <input type="text" class="form-control" required id="niqobah" maxlength="15" name="niqobah">
          </div>
          <div class="mb-3">
            <label for="pekerjaan" class="form-label">Pekerjaan<a style="color:red">*</a></label>
            <input type="text" class="form-control" required id="pekerjaan" maxlength="15" name="pekerjaan">
          </div>
          <div class="mb-3">
            <label for="tanggal_lahir" class="form-label">Tanggal lahir <a style="color:red">*</a></label>
            <input type="date" class="form-control" required id="tanggal_lahir" name="tanggal_lahir">
          </div>
          <div class="mb-3">
            <label for="tahun_masuk" class="form-label">Tahun masuk <a style="color:red">*</a></label>
            <input type="date" class="form-control" required id="tahun_masuk" name="tahun_masuk">
          </div>
          <div class="mb-3">
            <label for="tahun_keluar" class="form-label">Tahun keluar <a style="color:red">*</a></label>
            <input type="date" class="form-control" required id="tahun_keluar" name="tahun_keluar">
          </div>
          <div class="mb-3">
            <label for="foto" class="form-label">Foto <a id="star_edit_2"></a></label>
            <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">password  <a id="star_edit"></a></label>
            <input type="password" class="form-control" id="password" name="password">
          </div>
          <div class="row" id="hak_akses">
            @php $no = 1; @endphp
            @foreach ($roles as $item)
            <div class="col-md-4">
              <input type="checkbox" data-toggle="toggle" data-on="Yes" data-off="No" data-onstyle="success" data-offstyle="danger" data-size="sm" id="roles_{{$no}}" value="{{$item->nama_role}}" name="roles[]">
            </div>
            @php $no++; @endphp
            @endforeach
          </div>
          <div class="text-right"><a style="color:red">*</a> Wajib diisi</div>
        </div>
        <div class="modal-footer">
          @csrf
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
        </div>
    </div>
  </div>
</div>

{{-- Datatable JS --}}
<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script>
<script src="/assets/datatables/jzip.min.js"></script>
<script src="/assets/datatables/pdfmake.min.js"></script>
<script src="/assets/datatables/vfs_font.js"></script>
<script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

<script>
$(document).ready(function() {
  var aksi_status = true;

  $('#foto').change(function(e){
    var input = this;
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) { $('#img_view').attr('src', e.target.result); }
      reader.readAsDataURL(input.files[0]);
    }
  });

  var table = $('#tabel_anggota').DataTable({
    ajax: { url: '/tabel-anggota/data', method: 'get', dataSrc: 'data' },
    columns: [
      { data: null, render: function(data, type, row, meta) { return meta.row + 1; } },
      { data: 'nama' },
      { data: 'id_anggota' },
      { data: 'alamat' },
      { data: 'niqobah' },
      { data: function(data) { return moment(data.tahun_masuk).format('DD-MM-YYYY'); } }
    ],
    aoColumnDefs: [{
      targets: 6, data: 'id',
      "render": function(data, catatan, row) {
        return '<a class="" href="#" id="btn_edit" data-id="'+row.id+'" data-nama="'+row.nama+'" data-email="'+row.email+'" data-alamat="'+row.alamat+'" data-niqobah="'+row.niqobah+'" data-pekerjaan="'+row.pekerjaan+'" data-tanggal_lahir="'+row.tanggal_lahir+'" data-tahun_masuk="'+row.tahun_masuk+'" data-tahun_keluar="'+row.tahun_keluar+'" data-no_hp="'+row.no_hp+'" data-foto="'+row.foto+'" data-barcode="'+row.barcode+'" data-id_users="'+row.id_users+'" data-password="'+row.password+'" data-foto_lama="'+row.foto+'" data-roles_count="{{$roles_count}}"><i class="fas fa-edit"></i></a> <a class="" href="#" id="btn_deleted" data-id="'+row.id+'"><i class="fas fa-trash"></i></a> <a class="" href="/tabel-anggota/kta/'+row.id_users+'"><i class="far fa-arrow-alt-circle-right"></i></a>';
      }
    }],
    dom: 'Bfrtip',
    buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
  });

  $('#tabel_anggota tbody').on('click', '#btn_edit', function(e) {
    e.preventDefault();
    aksi_status = false;
    var id = this.getAttribute('data-id');
    var nama = this.getAttribute('data-nama');
    var email = this.getAttribute('data-email');
    var alamat = this.getAttribute('data-alamat');
    var niqobah = this.getAttribute('data-niqobah');
    var pekerjaan = this.getAttribute('data-pekerjaan');
    var tanggal_lahir = this.getAttribute('data-tanggal_lahir');
    var tahun_masuk = this.getAttribute('data-tahun_masuk');
    var tahun_keluar = this.getAttribute('data-tahun_keluar');
    var no_hp = this.getAttribute('data-no_hp');
    var foto = this.getAttribute('data-foto');
    var barcode = this.getAttribute('data-barcode');
    var id_users = this.getAttribute('data-id_users');
    var password = this.getAttribute('data-password');
    var foto_lama = this.getAttribute('data-foto_lama');

    $('#id_users').val(id_users);
    $('#barcode').val(barcode);
    $('#nama').val(nama);
    $('#email').val(email);
    $('#alamat').val(alamat);
    $('#niqobah').val(niqobah);
    $('#pekerjaan').val(pekerjaan);
    $('#tanggal_lahir').val(tanggal_lahir);
    $('#tahun_masuk').val(tahun_masuk);
    $('#tahun_keluar').val(tahun_keluar);
    $('#no_hp').val(no_hp);
    $('#foto_lama').val(foto_lama);
    $('#img_view').attr('src', '/storage/' + foto);
    $(`#star_edit`).html(`<a style="color:red">*</a>`);
    $(`#star_edit_2`).html(``);
    dataAkses(id_users);
    $('#modal_anggota').modal('show');
  });

  $('#btn_tambah').click(function(e){
    e.preventDefault();
    aksi_status = true;
    $(`#star_edit`).html(`<a style="color:red">*</a>`);
    $(`#star_edit_2`).html(`<a style="color:red">*</a>`);
    $('#modal_anggota').modal('show');
    clearData();
  });

  $('#form_anggota').submit(function(e){
    e.preventDefault();
    var data = new FormData(this);
    data.append('aksi', aksi_status);
    if(aksi_status){
      $.ajax({
        url: "/tabel-anggota/store",
        method: "POST",
        data: data,
        processData: false,
        contentType: false,
        success: function(data) {
          table.ajax.reload();
          $('#modal_anggota').modal('hide');
          Toast.fire({icon:'success',title:'Simpan Berhasil'});
        },
        error: function(data){ Toast.fire({icon:'error',title:data['responseJSON']['message']}); }
      });
    } else {
      $.ajax({
        url: "/tabel-anggota/edit",
        method: "POST",
        data: data,
        processData: false,
        contentType: false,
        success: function(data) {
          table.ajax.reload();
          $('#modal_anggota').modal('hide');
          Toast.fire({icon:'success',title:'Simpan Berhasil'});
        },
        error: function(data){ Toast.fire({icon:'error',title:data['responseJSON']['message']}); }
      });
    }
  });

  function dataAkses(id) {
    $.ajax({
      url: "/tabel-anggota/data-hak-akses",
      method: "POST",
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      data: {'id': id},
      success: function(data) {
        var no = 1;
        data['data'].forEach(element => {
          $(`input[value=${element.nama_role}]`).prop("checked", true);
          $(`input[value=${element.nama_role}]`).trigger("click");
          no++;
        });
      },
      error: function(data, exception){ Toast.fire({icon:'error',title:exception}); }
    });
  }

  $('#tabel_anggota tbody').on('click', '#btn_deleted', function(e) {
    e.preventDefault();
    var id = this.getAttribute('data-id');
    Swal.fire({
      title: 'Apa kamu yakin ingin hapus data ini?',
      text: "Data akan hilang setelah dihapus!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya hapus data ini!'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "/tabel-anggota/hapus",
          method: "POST",
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          data: {'id': id},
          success: function(data) {
            table.ajax.reload();
            Toast.fire({icon:'success',title:'Berhasil hapus data'});
          },
          error: function(data, exception){ Toast.fire({icon:'error',title:exception}); }
        });
      }
    });
  });

  function clearData(){
    var length = {{$roles_count}};
    for (let index = 1; index <= length; index++) {
      $(`#roles_${index}`).prop("checked", false);
      $(`#roles_${index}`).trigger("click");
    }
    $('#nama').val('');
    $('#email').val('');
    $('#alamat').val('');
    $('#niqobah').val('');
    $('#tanggal_lahir').val('');
    $('#tahun_masuk').val('');
    $('#tahun_keluar').val('');
    $('#foto').val('');
    $('#password').val('');
    $('#img_view').attr('src','/storage/');
    $('#no_hp').val('');
    $('#pekerjaan').val('');
  }
});
</script>
@endsection