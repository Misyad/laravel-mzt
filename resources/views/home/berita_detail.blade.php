@extends('home.master')
    
@section('konten')

<main id="main" style="margin-top: 30px">
    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <h2>Detail Berita</h2>
          <p><span>{!!$berita->judul!!}</span></p>
        </div>

        <div class="row gy-4">
            <div class="col-lg-12 text-center" >
                    <img src="/storage/{{$berita->foto}}" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
                  <div class="row content  mt-4">
                    <div class="content ps-0 ps-lg-5">
                      Pembuat :  {!!$berita->nama!!} <br>
                      Tanggal :  {!!date('d-m-Y', strtotime($berita->created_at))!!}
                      </div>
                  </div>
              </div>
          <div class="col-lg-12 d-flex align-items-start" data-aos="fade-up" data-aos-delay="300">
            <div class="content ps-0 ps-lg-5">
              {!!$berita->deskripsi!!}
            </div>
          </div>
      
        </div>

      </div>
    </section><!-- End About Section -->

  </main><!-- End #main -->
  @endsection

