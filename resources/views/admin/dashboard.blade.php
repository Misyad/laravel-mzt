@extends('admin.master')

@section('konten')
<style>
  .scroll { max-height: 600px; overflow-y: auto; height: 600px; }
</style>
<link rel="stylesheet" href="/stisla/node_modules/fullcalendar/dist/fullcalendar.min.css">
<meta name="csrf-token" content="{{ csrf_token() }}" />

<div class="mzt-page-header">
  <div>
    <h1>Dashboard</h1>
    <div class="description">Ringkasan aktivitas organisasi</div>
  </div>
</div>

<div class="mzt-grid mzt-grid-4 mzt-mb-4">
  <div class="mzt-stat-card">
    <div class="glow primary"></div>
    <div class="mzt-flex-between">
      <div>
        <div class="stat-label">Event</div>
        <div class="stat-value">{{$event}}</div>
      </div>
      <div class="stat-icon primary"><i class="far fa-calendar-alt"></i></div>
    </div>
  </div>
  <div class="mzt-stat-card">
    <div class="glow success"></div>
    <div class="mzt-flex-between">
      <div>
        <div class="stat-label">Event Selesai</div>
        <div class="stat-value">{{$event_selesai}}</div>
      </div>
      <div class="stat-icon success"><i class="far fa-calendar-check"></i></div>
    </div>
  </div>
  <div class="mzt-stat-card">
    <div class="glow gold"></div>
    <div class="mzt-flex-between">
      <div>
        <div class="stat-label">Event Upcoming</div>
        <div class="stat-value">{{$event_mendatang}}</div>
      </div>
      <div class="stat-icon gold"><i class="fas fa-redo-alt"></i></div>
    </div>
  </div>
  <div class="mzt-stat-card">
    <div class="glow info"></div>
    <div class="mzt-flex-between">
      <div>
        <div class="stat-label">Total Anggota</div>
        <div class="stat-value">{{$total_anggota}}</div>
      </div>
      <div class="stat-icon info"><i class="fas fa-user-friends"></i></div>
    </div>
  </div>
</div>

<div class="mzt-grid mzt-grid-2">
  <div class="mzt-card">
    <div class="mzt-card-header"><h4>Event</h4></div>
    <div class="mzt-card-body">
      <div class="mzt-table-wrap scroll">
        <table class="mzt-table">
          <thead><tr><th>Nama</th><th>Tanggal</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($event_all as $val)
            <tr>
              <td>{{$val->judul_event}}</td>
              <td>@php echo date('d/m/Y', strtotime($val->tanggal_mulai)).' - '.date('d/m/Y', strtotime($val->tanggal_selesai)); @endphp</td>
              <td>
                @php
                  if ($val->status == "Ongoing") { echo '<span class="mzt-badge mzt-badge-info">Ongoing</span>'; }
                  elseif ($val->status == "Complate") { echo '<span class="mzt-badge mzt-badge-success">Complate</span>'; }
                  elseif ($val->status == "Upcomming") { echo '<span class="mzt-badge mzt-badge-warning">Upcomming</span>'; }
                @endphp
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="mzt-card">
    <div class="mzt-card-header"><h4>Upcoming Event</h4></div>
    <div class="mzt-card-body"><div id="myEvent"></div></div>
  </div>
</div>

<script src="/stisla/node_modules/jquery-sparkline/jquery.sparkline.min.js"></script>
<script src="/stisla/node_modules/chart.js/dist/Chart.min.js"></script>
<script src="/stisla/node_modules/fullcalendar/dist/fullcalendar.min.js"></script>
<script>
$(document).ready(function() {
  $.ajax({
    url: "/dashboard/get-calender",
    method: "get",
    success: function(data) {
      $("#myEvent").fullCalendar({
        height: 'auto',
        header: { left: 'prev,next today', center: 'title', right: 'month' },
        editable: true,
        events: data['data']
      });
    },
    error: function(data, exception) { alert('server not responding...'); }
  });
});
</script>
@endsection