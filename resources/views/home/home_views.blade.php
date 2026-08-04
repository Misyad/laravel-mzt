@extends('home.master')
    
@section('konten')

<div id="carouselExampleControls" class="carousel slide" data-ride="carousel">
  <div class="carousel-inner">
    <div class="carousel-item active">
      @if (isset($carosel->foto))
       <img class="d-block w-100"  src="/storage/{{$carosel->foto}}" alt="First slide">
      @else
        <img class="d-block w-100"  src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d1/Image_not_available.png/800px-Image_not_available.png?20210219185637" alt="First slide">
      @endif
    </div>
  </div>
</div>
<main id="main">
    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <h2>Info</h2>
          <p>Info <span>Pesanteren</span></p>
        </div>

        <div class="row gy-4">
      
          <div class="col-lg-7 d-flex align-items-start" data-aos="fade-up" data-aos-delay="300">
            <div class="content ps-0 ps-lg-5">
              {!!$info_pesantren->deskripsi!!}
            </div>
          </div>
          <div class="col-lg-5 position-relative " >
            @if (isset($info_pesantren->foto))
              <img src="/storage/{{$info_pesantren->foto}}" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
            @else
              <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d1/Image_not_available.png/800px-Image_not_available.png?20210219185637" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
            @endif
            
              <div class="row content  mt-4">
                <div class="col-md-12">Alamat :</div>
                <div class="col-md-12 mt-1">
                  {!!$info_pesantren->alamat!!}
                  
                  </div>
                  <div class="col-md-12 mt-2">Telepon:</div>
                  <div class="col-md-12 mt-1">{!!$info_pesantren->telpon!!}</div>
              </div>
          </div>
        </div>

      </div>
    </section><!-- End About Section -->

    <!-- ======= Chefs Section ======= -->
    <section id="chefs" class="chefs section-bg">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <h2>Berita</h2>
          <p>Berita<span>Terbaru</span></p>
        </div>

        <div class="row gy-4">

          @foreach ($berita as $item)

          <div  class="col-lg-4 col-md-6 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="100">
            <div  class="chef-member">
              <div class="member-img">
                  <img  src="/storage/{{$item->foto}}"  class="img-fluid" alt="">
                  </div>
              <div class="member-info">
              <a href="/berita/{{$item->slug}}">
                <h4>{{$item->judul}}</h4>
              </a>
                <span>Pembuat : {{$item->nama}}</span>
                <span>Tanggal : {!!date('d-m-Y', strtotime($item->created_at))!!}</span>
                @php
                   echo substr($item->deskripsi,0,250);
                @endphp
                <a >....</a>
              </div>
            </div>
          </div><!-- End Chefs Member -->

          @endforeach

        </div>

      </div>
    </section><!-- End Chefs Section -->
  </main><!-- End #main -->
  @endsection

