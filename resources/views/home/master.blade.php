<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Maziltu Tholiban</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="/assets/logo-pondok.jpg" rel="icon">
  <link href="/assets/logo-pondok.jpg" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600;1,700&family=Amatic+SC:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="/assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="/assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <script src="/assets/jquery.min.js"></script>
  <!-- Template Main CSS File -->
  <link href="/assets/css/main.css" rel="stylesheet">

</head>

<body>
  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="container d-flex align-items-center justify-content-between">

      <a href="/" class="logo d-flex align-items-center me-auto me-lg-0">
        <!-- Uncomment the line below if you also wish to use an image logo -->
        <div class="d-flex align-items-center">
          <img src="/assets/logo-pondok.jpg" alt=""> 
          <div class="h6 mt-2">Maziltu Tholiban</div>
        </div>
      </a>

      <nav id="navbar" class="navbar">
        <ul>
          <li><a  href="/"  class=" {{ '' == request()->segment(1) ? 'active':'' }}">Info pesanteren   </a></li>
          <li class="dropdown"><a href="#" class="
             {{ 'berita' == request()->segment(1) ? 'active':'' }}
             {{ 'pengumunan' == request()->segment(1) ? 'active':'' }}
             "><span>Berita dan Pengumuman</span> <i class="bi bi-chevron-down dropdown-indicator"></i></a>
            <ul>
              <li><a href="/berita" >Berita</a></li>
              <li><a href="/pengumunan">Pengumuman</a></li>
            </ul>
          </li>
          <li><a href="/tentang-mzt" class=" {{ 'tentang-mzt' == request()->segment(1) ? 'active':'' }}">Tentang MZT</a></li>
        </ul>
      </nav><!-- .navbar -->

      @auth
              
        <a class="btn-book-a-table" href="/logout">Logout</a>
          @else
          <a class="btn-book-a-table" href="/login">Login</a>
      @endauth

      <i class="mobile-nav-toggle mobile-nav-show bi bi-list"></i>
      <i class="mobile-nav-toggle mobile-nav-hide d-none bi bi-x"></i>

    </div>
  </header><!-- End Header -->


  <!-- End Hero Section -->



      <!-- Main Content -->
      @yield('konten')   




  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">

    <div class="container">
      <div class="row gy-3">
        <div class="col-lg-6 col-md-6 d-flex">
          <i class="bi bi-geo-alt icon"></i>
          <div>
            <h4>Alamat</h4>
            {{$tentang_mzt->alamat}}
          </div>

        </div>

        <div class="col-lg-6 col-md-6 footer-links d-flex">
          <i class="bi bi-telephone icon"></i>
          <div>
            <h4>Telpon</h4>
            <p>
              <strong>Phone : </strong>{{$tentang_mzt->telpon}} <br>
              {{-- <strong>Email:</strong> info@example.com<br> --}}
            </p>
          </div>
        </div>

      </div>
    </div>

    <div class="container">
      <div class="credits">
        <!-- All the links in the footer should remain intact. -->
        <!-- You can delete the links only if you purchased the pro version. -->
        <!-- Licensing information: https://bootstrapmade.com/license/ -->
        <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/yummy-bootstrap-restaurant-website-template/ -->
        Designed by <a>Maziltu Tholiban</a>
      </div>
    </div>

  </footer><!-- End Footer -->
  <!-- End Footer -->

  <a href="#" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <div id="preloader"></div>

  <!-- Vendor JS Files -->
  <script src="//cdn.jsdelivr.net/npm/sweetalert2@10"></script>
  <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/vendor/aos/aos.js"></script>
  <script src="/assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="/assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="/assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="/assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="/assets/js/main.js"></script>

</body>

</html>