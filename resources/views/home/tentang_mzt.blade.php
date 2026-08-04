@extends('home.master')
    
@section('konten')

<main id="main" style="margin-top: 30px">
    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <h2>Tentang</h2>
          <p>Tentang  <span>Maziltu Tholiban</span></p>
        </div>

        <div class="row gy-4">
      
          <div class="col-lg-7 d-flex align-items-start" data-aos="fade-up" data-aos-delay="300">
            <div class="content ps-0 ps-lg-5">
              {!!$tentang_mzt->deskripsi!!}
            </div>
          </div>
          <div class="col-lg-5 position-relative " >
            @if (isset($tentang_mzt->foto))
                
            <img src="/storage/{{$tentang_mzt->foto}}" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
            @else
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d1/Image_not_available.png/800px-Image_not_available.png?20210219185637" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
                
            @endif
              <div class="row content  mt-4">
                <div class="col-md-12">Alamat :</div>
                <div class="col-md-12 mt-1">
                  {!!$tentang_mzt->alamat!!}
                  
                  </div>
                  <div class="col-md-12 mt-2">Telepon:</div>
                  <div class="col-md-12 mt-1">{!!$tentang_mzt->telpon!!}</div>
              </div>
          </div>
        </div>

      </div>
    </section><!-- End About Section -->

  </main><!-- End #main -->
  @endsection

