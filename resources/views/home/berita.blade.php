@extends('home.master')
    
@section('konten')
<meta name="csrf-token" content="{{ csrf_token() }}" />
<style>
    .lds-ring {
        display: inline-block;
        position: relative;
        width: 80px;
        height: 80px;
        }
        .lds-ring div {
        box-sizing: border-box;
        display: block;
        position: absolute;
        width: 64px;
        height: 64px;
        margin: 8px;
        border: 8px solid rgb(116, 112, 112);
        border-radius: 50%;
        animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        border-color: rgb(85, 80, 80) transparent transparent transparent;
        }
        .lds-ring div:nth-child(1) {
        animation-delay: -0.45s;
        }
        .lds-ring div:nth-child(2) {
        animation-delay: -0.3s;
        }
        .lds-ring div:nth-child(3) {
        animation-delay: -0.15s;
        }
        @keyframes lds-ring {
        0% {
        transform: rotate(0deg);
        }
        100% {
        transform: rotate(360deg);
        }
        }

</style>
<main id="main" style="margin-top: 30px">
    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">

        <div class="section-header">
          <p><span>Berita</span></p>
        </div>
        @if ($count != 0)
            @foreach ($berita as $item)
            <div id="berita_add">
                <div class="row gy-4 mt-2 post-id" id="{{$item->created_at}}">
                    <div class="col-lg-12 col-md-12 text-center">
                        <p><h4>{{$item->judul}}</h4></p>
                        Pembuat : {{$item->nama}}<br>
                        Tanggal :{!!date('d-m-Y', strtotime($item->created_at))!!}
                    </div>
                    <div class="col-lg-5 position-relative " >
                        <img src="/storage/{{$item->foto}}" class="about-img img-fluid" alt="" srcset="" data-aos="fade-up" data-aos-delay="150">
                    </div>
                    <div class="col-lg-7 d-flex align-items-start" data-aos="fade-up" data-aos-delay="300">
                        <div class="content ps-0 ps-lg-5">
                        @php
                        echo substr($item->deskripsi,0,1000);
                            @endphp
                            <a href="/berita/{{$item->slug}}">Selengkapnya.....</a>
                        </div>
                    </div>
                
                </div>
            </div>
        @endforeach
        @else
        <div class="row" style="margin-bottom: 175px; margin-top : 175px">
            <div class="col-md-12 text-center mt-4 mb-4" >
                <p><h4>Tidak ada berita</h4></p>
            </div>
        </div>
        @endif



        <div class="d-flex justify-content-center mt-2"> {{ $berita->links('pagination::bootstrap-4') }}</div>
    </section><!-- End About Section -->

  </main><!-- End #main -->


      
  @endsection

