<!DOCTYPE html>
<html>
<head>
    <title>KTA </title>
</head>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Roboto+Slab:wght@500;600&display=swap" rel="stylesheet">
<style>

@page {
        margin: 0;
    }


</style>
<script src="/stisla/assets/jquery.min.js"></script>
<body>
  <div style="height: 122mm; width: 94mm; position: relative; font-family: 'Roboto Slab', serif;">
    <img  src="{{ asset('/assets/KTA Musan FixArtboard 1.jpg') }}" alt="" srcset="">
    <img
        style="width: 68mm; height: 93mm; object-fit: cover; position: absolute; top: 180px; left: 62px; border-radius: 10px;"
        src="{{ asset($profil) }}"
    alt="Photo">
    <img
        style="width: 600px; object-fit: cover; position: absolute; top: 560px; left: 62px; "
        src="{{ asset('storage/' . $bracode) }}"
    alt="Photo">

    <div style="position: absolute; width: 650px; left: 350px; top: 200px ; font-size : 25px; " class="text-center" >
      <table >
        <tr style="margin-bottom: 200px">
          <td>ID anggota </td>
          <td >:</td>
          <td>{{$id_anggota}}</td>
      </tr>
          <tr>
            <td>Nama</td>
            <td >:</td>
            <td>{{$nama}}</td>
          </tr>
          <tr>
            <td>Alamat</td>
            <td >:</td>
            <td  rowspan="2">@php
              echo $alamat;
            @endphp</td>
          </tr>
          <tr>
            <td></td>
            <td ></td>
            <td></td>
          </tr>
          <tr>
            <td style="width: 180px">Niqobah</td>
            <td >:</td>
            <td>{{$niqobah}}</td>
          </tr>
          <tr>
              <td >Tahun Masuk</td>
              <td >:</td>
              <td>{{$tahun_masuk}}</td>
          </tr>
          <tr>
              <td >Tahun Keluar</td>
              <td >:</td>
              <td>{{$tahun_keluar}}</td>
          </tr>
        </table>
  </div>
  </div>
</body>
</html>
<script>
  $(document).ready(function() {
      window.print();
    });
</script>