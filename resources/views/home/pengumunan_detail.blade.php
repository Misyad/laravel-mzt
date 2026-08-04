@extends('home.master')

@section('konten')
<script type="text/javascript"
src="{{env('MIDTRANS_URL')}}"
data-client-key="{{env('MIDTRANS_CLIENT_KEY')}}"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<meta name="csrf-token" content="{{ csrf_token() }}" />
<main id="main" style="margin-top: 30px">
    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <h2>Detail Pengumuman</h2>
          <p><span>{!!$event->judul_event!!}</span></p>
        </div>

        <div class="row gy-4">
            <div class="col-lg-12 text-center" >

                @if ($event->banner)

                <img src="/storage/{{$event->banner}}" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
                @endif

                <div class="row content  mt-4">
                    <div class="content ps-0 ps-lg-5">
                        <p><h4>{{$event->judul_event}}</h4></p>
                        Status :
                        @switch($event->status)
                            @case("Ongoing")
                                <span style="background-color: aqua; color:black" class="badge badge-primary">Ongoing</span>
                            @break
                            @case("Upcomming")
                               <span style="background-color: #ffc107; color:black" class="badge badge-success">Upcomming</span>
                            @break
                            @case("Complate")
                                <span style="background-color: #3e9e42; color:black" class="badge badge-success">Complate</span>
                            @break

                            @default
                                tidak ada
                        @endswitch

                        <br class="mt-2">
                        Tempat: {{$event->lokasi}}
                        <br class="mt-2">
                        Tanggal : {!!date('d-m-Y', strtotime($event->tanggal_mulai))!!} Sampai {!!date('d-m-Y', strtotime($event->tanggal_selesai))!!}
                      </div>
                  </div>
              </div>
          <div class="col-lg-12 d-flex align-items-start" data-aos="fade-up" data-aos-delay="300">
            <div class="content ps-0 ps-lg-5">
              {!!$event->deskripsi!!}
            </div>
          </div>
          @if ($status_tanggal)
          <div class="col-lg-12" data-aos="fade-up" data-aos-delay="300">
            <div class="content ps-0 ps-lg-5 text-center mb-3  mt-3">
                <a id="btn_modal_bayar" onclick="showModal()" class="btn btn-success">Daftar</a>
            </div>
          </div>
          @endif
          <div class="col-lg-12" data-aos="fade-up" data-aos-delay="300">
            <div class="ps-0 ps-lg-5">
            <table class="table table-hover">
                <thead>
                  <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Jam</th>
                  </tr>
                </thead>
                <tbody>
                    @foreach ($tanggal_event as $item)
                    <tr>
                        <td>{!!date('d-m-Y', strtotime($item->tanggal))!!}</td>
                        @if ($item->set_jam  == 'dijam')
                           <td>{!!date('h:i', strtotime($item->jam_mulai))!!} - {{$item->jam_selesai}}</td>
                        @else
                          <td>Full Day</td>
                        @endif

                      </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
          </div>

        </div>



      </div>
    </section><!-- End About Section -->

  </main><!-- End #main -->

  <!-- Modal -->
<div class="modal fade" id="modal_bayar" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header text-center">
        <h5 class="modal-title" id="staticBackdropLabel">Isi Data Pembayaran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="/pengumunan/infak/pembayaran" method="POST" id="formDaftar">
          @csrf
          <div class="mb-3">
            <label for="nama" class="form-label">Nama <a style="color : red">*</a></label>
            <input type="text" class="form-control" required id="nama" maxlength="40"  oninvalid="this.setCustomValidity('Nama Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')"  name="nama" placeholder="Isikan Dengan Nama Anda">
          </div>
          <div class="mb-3">
            <label for="nama" class="form-label">Nomer telpon <a style="color : red">*</a></label>
            <input type="number" class="form-control numberonly" maxlength="20" required id="nomer_telpon"  name="nomer_telpon" oninvalid="this.setCustomValidity('Nomer telpon Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')"   placeholder="Contoh Nomer telpon : 08123231331">
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control"  id="email"  name="email"   placeholder="Isikan dengan email anda">
          </div>
          <div class="mb-3">
            <label for="alamat" class="form-label">Alamat <a style="color : red">*</a></label>
            <textarea class="form-control" id="alamat" required maxlength="80" name="alamat" rows="3" oninvalid="this.setCustomValidity('Alamat Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')" ></textarea>
          </div>
          <div class="mb-3">
            <label for="niqobah" class="form-label">Niqobah<a style="color : red">*</a></label>
            <input type="text" class="form-control" required id="niqobah" maxlength="20" name="niqobah" aria-describedby="tanggal_lahir" placeholder="Isikan dengan niqobah anda" oninvalid="this.setCustomValidity('Niqobah Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')">
          </div>
          <div class="mb-3">
            <label for="pekerjaan" class="form-label">Pekerjaan</label>
            <input type="text" class="form-control"  id="pekerjaan" maxlength="20" name="pekerjaan" aria-describedby="tanggal_lahir" placeholder="Isikan dengan pekerjaan anda">
          </div>
          <div class="mb-3">
            <label for="tempat_lahir" class="form-label">Tempat Lahir <a style="color : red">*</a></label>
            <input type="text" required class="form-control"  id="tempat_lahir" maxlength="20" name="tempat_lahir" aria-describedby="tanggal_lahir" placeholder="Isikan dengan tempat lahir anda">
          </div>
          <div class="mb-3">
            <label for="tanggal_lahir" class="form-label">Tanggal lahir <a style="color : red">*</a></label>
            <input type="date" class="form-control" required id="tanggal_lahir" name="tanggal_lahir" aria-describedby="tanggal_lahir" oninvalid="this.setCustomValidity('Tanggal lahir tidak boleh Kosong.')" onchange="this.setCustomValidity('')">
          </div>
          <div class="mb-3">
            <label for="tahun_masuk" class="form-label">Tahun masuk <a style="color : red">*</a></label>
            <input type="date" class="form-control" required id="tahun_masuk" name="tahun_masuk" aria-describedby="tanggal_lahir" oninvalid="this.setCustomValidity('Tahun masuk Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')">
          </div>
          <div class="mb-3">
              <label for="tahun_keluar" class="form-label">Tahun keluar <a style="color : red">*</a></label>
              <input type="date" class="form-control" required id="tahun_keluar" name="tahun_keluar" aria-describedby="tanggal_lahir" oninvalid="this.setCustomValidity('Tahun keluar Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')">
          </div>
          <div class="mb-3">
              <label for="infak" class="form-label">Infak </label>
              <div class="input-group">
              <input type="text" class="form-control"    id="infak" name="infak" aria-describedby="infak">
                <button class="btn btn-outline-secondary" id="upButton" type="button">+</button>
                <button class="btn btn-outline-secondary" id="downButton" type="button">-</button>
              </div>

          </div>
          <div class="mb-3">
            <label for="foto" class="form-label">foto </label>
            <input type="file" class="form-control"  id="foto" name="foto" aria-describedby="foto" oninvalid="this.setCustomValidity('Foto Tidak Boleh Kosong.')" onchange="this.setCustomValidity('')">
            <small style="color :red;">Format foto harus: jpg atau png | Foto diri menggunakan baju koko dan kofyah putih</small>
          </div>
          <div class="mb-3 text-center">
            <div class="g-recaptcha" data-sitekey="6LfyeFsqAAAAADshwLvT2CTPs_y1YBjjw7dztckP" required></div>
          </div>
          <div class="text-end"><a style="color : red">*</a> Wajib diisi</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>

  $(document).ready(function () {
    const $recaptcha = document.querySelector('.g-recaptcha-response');
    if ($recaptcha) {
      $recaptcha.setAttribute('required', 'required');
    }
      $('.numberonly').keypress(function (e) {

          var charCode = (e.which) ? e.which : event.keyCode
          if (String.fromCharCode(charCode).match(/[^0-9]/g))
              return false;
      });

  });

  $('#infak').on('input', function (e) {
    var inputValue = $(this).val();
    if (inputValue) {
        var numericValue = unformatRupiah(inputValue);
        if (numericValue < '10000') {
            numericValue = '10000'; // Memastikan minimal 100 ribu
        }
        $(this).val(formatRupiah(numericValue, 'Rp. '));
    }
});

  $('#upButton').click(function() {
      var inputValue = $('#infak').val();
      if(inputValue == ''){
        inputValue = '10000';
        var numericValue = unformatRupiah(inputValue);
      }else{
        var numericValue = unformatRupiah(inputValue);
        numericValue += 20000; // Menambahkan 50 ribu
        if (numericValue < '10000') {
            numericValue = '10000'; // Memastikan minimal 100 ribu
        }
    }
    $('#infak').val(formatRupiah(numericValue, 'Rp. '));

  });

  $('#downButton').click(function() {
      var inputValue = $('#infak').val();
      var numericValue = unformatRupiah(inputValue);
      numericValue -= 20000; // Mengurangkan 50 ribu
      if (numericValue <= '' || isNaN(numericValue)) {
          numericValue = ''; // Memastikan minimal 100 ribu
          $('#infak').val('');
      }else{
         $('#infak').val(formatRupiah(numericValue, 'Rp. '));
      }

  });


    function formatRupiah(angka, prefix) {
        var rupiah = ''; // Inisialisasi variabel rupiah

        if (typeof angka === 'string') {
            // Jika angka adalah string, kita langsung gunakan
            rupiah = angka;
        } else if (typeof angka === 'number') {
            // Jika angka adalah number, kita konversi ke string dan gunakan
            rupiah = angka.toString();
        } else {
            // Jika angka tidak sesuai, beri nilai default
            rupiah = '0';
        }

        var split = rupiah.split(',');
        var sisa = split[0].length % 3;
        rupiah = split[0].substr(0, sisa);
        var ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;

        if (prefix == undefined) {
            return rupiah;
        } else {
            return rupiah ? prefix + rupiah : prefix;
        }
    }


    function unformatRupiah(rupiah) {
        return parseInt(rupiah.replace(/[^0-9]/g, ''), 10);
    }

  function showModal()
  {
    $('#modal_bayar').modal('show');
    $('#formDaftar')[0].reset();

  }

  $('#formDaftar').on('submit', function(e){
    e.preventDefault();
    var data = new FormData(this);
    data.append('harga','{{$harga}}');
    data.append('id_event','{{$id_event}}');
    $.ajax({
            url: "/pengumunan/infak/pembayaran",
            method: "POST",
            data:  data,
            headers: {
                          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                      },
            processData: false,
            contentType: false,
            success: function(response) {
              if(response.success){
                $('#modal_bayar').modal('hide');
                // console.log(response.data.snap,response.data.id_anggota);
                if(response.data == 'null'){
                  Swal.fire(response.message, response.body, "success");
                }else{
                    Swal.fire(response.message, response.data, "success");
                }
              }else{
                Swal.fire(response.message, response.data, "error");
              }
            },
            error: function(response){
              Swal.fire('Pendaftaran Gagal!',response.responseJSON.message, "error");
              console.log(response.responseJSON.message);
            }
        });

  });

  function snapPay(token, id_anggota)
  {
    window.snap.pay(token, {
        onSuccess: function(result){
          /* You may add your own implementation here */
          send_data(result,id_anggota,token);
        },
        onPending: function(result){
          /* You may add your own implementation here */
          send_data(result,id_anggota,token);
        },
        onError: function(result){
          /* You may add your own implementation here */
          send_data(result,id_anggota,token);
        },
        onClose: function(){
          /* You may add your own implementation here */
          alert('you closed the popup without finishing the payment');
        }
      });

      function send_data(result,id_anggota)
      {
        $.ajax({
                      url: "/transaksi/pembayaran",
                      method: "POST",
                      headers: {
                          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                      },
                      data: {'result' : result , 'id_anggota' : id_anggota , 'snaptoken' : token},
                      success: function(response) {
                        if(response.success){
                          Swal.fire(response.message, response.data, "success");
                        }else{
                          Swal.fire(response.message, response.data, "error");
                        }
                      },
                      error: function(response, exception){
                        Swal.fire(response.message,response.responseJSON.data, "error");
                          console.log(response.responseJSON.message);
                      }
                    });
      }

  }

</script>
  @endsection

