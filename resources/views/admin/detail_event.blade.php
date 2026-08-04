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

<div class="mzt-page-header">
  <div><h1>Event Detail</h1><div class="description">Detail waktu kegiatan</div></div>
</div>

<div class="mzt-card mzt-mb-4"><div class="mzt-card-body">
  <div class="mzt-text-center mzt-mb-4"><h4>{{$data->judul_event}}</h4></div>
  @if ($data->banner)
    <div class="mzt-text-center mzt-mb-4"><img alt="image" src="/storage/{{$data->banner}}" class="img-fluid" style="border-radius:12px;max-height:300px;"></div>
  @endif
  @php echo $data->deskripsi; @endphp
</div></div>

<div class="mzt-card"><div class="mzt-card-header"><h4>Waktu Kegiatan</h4></div><div class="mzt-card-body"><div class="mzt-table-wrap">
  <table class="table table-bordered" id="tabel_event" width="100%" cellspacing="0">
    <thead><tr><th>Tanggal</th><th>Jam</th><th>aksi</th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><th>Tanggal</th><th>Jam</th><th>aksi</th></tr></tfoot>
  </table>
</div></div></div>

<div class="modal fade bd-example-modal-lg" id="modal_event" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Waktu Kegiatan</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
      <form id="form_event">
        <div class="mb-3"><label for="judul" class="form-label">Full day:</label><input type="checkbox" data-toggle="toggle" value="full_day" id="full_day"><input type="hidden" class="form-control" id="id_event" name="id_event"></div>
        <div class="mb-3"><div class="row">
          <div class="col-md-6"><label for="start_date" class="form-label">Waktu Mulai</label><input type="text" class="form-control" required id="start_date" name="start_date"></div>
          <div class="col-md-4"><label for="end_date" class="form-label">Waktu Selesai</label><input type="text" class="form-control" id="end_date" name="end_date"></div>
          <div class="col-md-2"><label>Sampai selesai</label><br><input type="checkbox" data-toggle="toggle" value="sampai_selesai" id="sampai_selesai"></div>
        </div></div>
        <div class="text-right"><a style="color:red">*</a> Wajib diisi</div>
      </div>
      <div class="modal-footer">@csrf<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Simpan</button></form></div>
  </div></div>
</div>

<script src="/assets/vendor_datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor_datatables/dataTables.bootstrap4.min.js"></script>
<script src="/assets/datatables/buttons1.min.js"></script><script src="/assets/datatables/jzip.min.js"></script><script src="/assets/datatables/pdfmake.min.js"></script><script src="/assets/datatables/vfs_font.js"></script><script src="/assets/datatables/buttonhtml5.min.js"></script>
<script src="/stisla/assets/js/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>

@if(in_array('event', $status_akses))
<script>
$(document).ready(function() {
  var aksi_status = true;
  var id = {{$data->id}};
  var table = $('#tabel_event').DataTable({
    ajax:{url:'/tabel-event/detail/data',method:'post',dataSrc:'data',headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id':{{$data->id}}}},
    columns:[{data:'tanggal'},{data:function(data){if(data.set_jam=='seharian'){return 'full day'}else{return moment(data.jam_mulai,'HH:mm:ss.SSSS').format('HH:mm')+' - '+data.jam_selesai}}}],
    aoColumnDefs:[{targets:2,data:'id',"render":function(data,catatan,row){
      return '<a href="#" id="btn_edit" data-id="'+row.id+'" data-tanggal="'+row.tanggal+'" data-jam_mulai="'+row.jam_mulai+'" data-jam_selesai="'+row.jam_selesai+'" data-set_jam="'+row.set_jam+'"><i class="fas fa-clock"></i></a> <a href="#" id="btn_deleted" data-id="'+row.id+'" data-start_date="'+row.jam_mulai+'" data-end_date="'+row.jam_selesai+'" data-set_jam="'+row.set_jam+'"><i class="fas fa-trash"></i></a> <a href="/tabel-event/detail/{{$data->id}}/'+row.id+'/prisensi"><i class="far fa-arrow-alt-circle-right"></i></a>';
    }}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });

  $('#tabel_event tbody').on('click','#btn_edit',function(e){
    e.preventDefault();aksi_status=false;
    var id=this.getAttribute('data-id'),tanggal=this.getAttribute('data-tanggal'),jam_mulai=this.getAttribute('data-jam_mulai'),jam_selesai=this.getAttribute('data-jam_selesai'),set_jam=this.getAttribute('data-set_jam');
    $('#id_event').val(id);
    if(set_jam=='seharian'){$('#full_day').prop('checked',true);$('#full_day').trigger('click');}
    else{$('#start_date').val(moment(jam_mulai,'HH:mm:ss').format('HH:mm'));$('#end_date').val(jam_selesai);}
    $('#modal_event').modal('show');
  });

  $('#tabel_event tbody').on('click','#btn_deleted',function(e){
    e.preventDefault();
    var id=this.getAttribute('data-id'),start_date=this.getAttribute('data-start_date'),end_date=this.getAttribute('data-end_date'),set_jam=this.getAttribute('data-set_jam');
    var status_d=(set_jam=='seharian')?'full_day':'';var status_s=(set_jam=='seharian')?'sampai_selesai':'';
    var id_event={{$data->id}};
    Swal.fire({title:'Apa kamu yakin ingin hapus data ini?',text:"Data akan hilang setelah dihapus!",icon:'warning',showCancelButton:true,confirmButtonColor:'#3085d6',cancelButtonColor:'#d33',confirmButtonText:'Ya hapus data ini!'}).then((result)=>{
      if(result.isConfirmed){$.ajax({url:"/tabel-event/detail/save",method:"POST",headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id_event':id_event,'start_date':start_date,'end_date':end_date,'full_day':status_d,'sampai_selesai':status_s},success:function(data){table.ajax.reload();$('#modal_event').modal('hide');Toast.fire({icon:'success',title:'Berhasil hapus data'});},error:function(data,exception){Toast.fire({icon:'error',title:exception});}});}
    });
  });

  $('#btn_edit_header').click(function(e){e.preventDefault();aksi_status=true;$('#modal_event').modal('show');$('#start_date').val('');$('#end_date').val('');});

  $('#form_event').submit(function(e){
    e.preventDefault();
    var start_date=$('#start_date').val(),end_date=$('#end_date').val(),id_event={{$data->id}};
    var status_d=($('#full_day').prop('checked'))?'full_day':'';var status_s=($('#sampai_selesai').prop('checked'))?'sampai_selesai':'';
    $.ajax({url:"/tabel-event/detail/save",method:"POST",headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id_event':id_event,'start_date':start_date,'end_date':end_date,'full_day':status_d,'sampai_selesai':status_s},
      success:function(data){table.ajax.reload();$('#modal_event').modal('hide');Toast.fire({icon:'success',title:'Simpan Berhasil'});},
      error:function(data,exception){Toast.fire({icon:'error',title:exception});}
    });
  });

  $('#full_day').change(function(e){e.preventDefault();var status=$('#full_day').prop('checked');statusdata(status);});
  $('#sampai_selesai').change(function(e){e.preventDefault();var status=$('#sampai_selesai').prop('checked');if(status){$("#end_date").prop('disabled',true);$("#end_date").val('');}else{$("#end_date").prop('disabled',false);$("#end_date").val('');}});
  function statusdata(status){if(status){$("#start_date").prop('disabled',true);$("#end_date").prop('disabled',true);$("#sampai_selesai").prop('disabled',true);$('#sampai_selesai').prop("checked",true);$('#sampai_selesai').trigger("click");cleardesabled();}else{$("#start_date").prop('disabled',false);$("#end_date").prop('disabled',false);$("#sampai_selesai").prop('disabled',false);$('#sampai_selesai').prop("checked",false);$('#sampai_selesai').trigger("click");cleardesabled();}}
  function cleardesabled(){$("#start_date").val('');$("#end_date").val('');}
  $('#start_date').datetimepicker({datepicker:false,format:'H:i'});
  $('#end_date').datetimepicker({datepicker:false,format:'H:i'});
});
</script>
@else
<script>
$(document).ready(function() {
  var table = $('#tabel_event').DataTable({
    ajax:{url:'/tabel-prisensi/detail/data',method:'post',dataSrc:'data',headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},data:{'id':{{$data->id}}}},
    columns:[{data:'tanggal'},{data:function(data){if(data.set_jam=='seharian'){return 'full day'}else{return moment(data.jam_mulai,'HH:mm:ss.SSSS').format('HH:mm')+' - '+data.jam_selesai}}}],
    aoColumnDefs:[{targets:2,data:'id',"render":function(data,catatan,row){
      return '<a href="/tabel-prisensi/detail/{{$data->id}}/'+row.id+'/prisensi"><i class="far fa-arrow-alt-circle-right"></i></a>';
    }}],
    dom:'Bfrtip',buttons:['copy','csv','excel','pdf','print']
  });
});
</script>
@endif
@endsection