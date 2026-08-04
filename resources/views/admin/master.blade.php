<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>MZT APPS - Maziltu Tholiban</title>

  <link href="/assets/logo-pondok.jpg" rel="icon">
  <link href="/assets/logo-pondok.jpg" rel="apple-touch-icon">

  {{-- Bootstrap 4 (needed for DataTables, modals) --}}
  <link rel="stylesheet" href="/stisla/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">

  {{-- OrgOS Design System --}}
  <link rel="stylesheet" href="/assets/css/mzt-orgos.css">

  <script src="/stisla/assets/jquery.min.js"></script>
  <script src="/assets/sweetalert.min.js"></script>
</head>

<body class="mzt-orgos">
@php $status_akses = $status_akses ?? []; $foto_profil = $foto_profil ?? ''; @endphp
<div id="app">
<div class="mzt-layout">

  {{-- Sidebar --}}
  <aside class="mzt-sidebar" id="mztSidebar">
    <div class="mzt-sidebar-header">
      <div class="mzt-sidebar-logo">MZ</div>
      <div class="mzt-sidebar-header-text mzt-sidebar-text">
        <div class="title">Maziltu Tholiban</div>
        <div class="subtitle">MZT &middot; Admin Panel</div>
      </div>
    </div>

    <div class="mzt-sidebar-content">

      @if (in_array('dashboard', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Dashboard</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/dashboard" class="{{ 'dashboard' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-tachometer-alt"></i>
              <span class="mzt-sidebar-text">Dashboard</span>
            </a>
          </li>
        </ul>
      </div>
      @endif

      @if (in_array('anggota', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Anggota</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/tabel-anggota" class="{{ 'tabel-anggota' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-users"></i>
              <span class="mzt-sidebar-text">Anggota</span>
            </a>
          </li>
        </ul>
      </div>
      @endif

      @if (in_array('prisensi', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Presensi</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/tabel-prisensi" class="{{ 'tabel-prisensi' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-clipboard-list"></i>
              <span class="mzt-sidebar-text">Presensi</span>
            </a>
          </li>
        </ul>
      </div>
      @endif

      @if (in_array('event', $status_akses) || in_array('berita', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Event dan Berita</div>
        <ul class="mzt-sidebar-menu">
          @if (in_array('event', $status_akses))
          <li class="mzt-sidebar-item">
            <a href="/tabel-event" class="{{ 'tabel-event' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-calendar-alt"></i>
              <span class="mzt-sidebar-text">Event</span>
            </a>
          </li>
          @endif
          @if (in_array('berita', $status_akses))
          <li class="mzt-sidebar-item">
            <a href="/tabel-berita" class="{{ 'tabel-berita' == request()->segment(1) ? 'active':'' }}">
              <i class="far fa-newspaper"></i>
              <span class="mzt-sidebar-text">Berita</span>
            </a>
          </li>
          @endif
          @if (in_array('id_card', $status_akses))
          <li class="mzt-sidebar-item">
            <a href="/id-card" class="{{ 'id-card' == request()->segment(1) ? 'active':'' }}">
              <i class="far fa-id-card"></i>
              <span class="mzt-sidebar-text">ID Card</span>
            </a>
          </li>
          @endif
          @if (in_array('event', $status_akses))
          <li class="mzt-sidebar-item">
            <a href="/tabel-event-transaksi" class="{{ 'tabel-event-transaksi' == request()->segment(1) ? 'active':'' }}">
              <i class="far fa-credit-card"></i>
              <span class="mzt-sidebar-text">Transaksi</span>
            </a>
          </li>
          @endif
        </ul>
      </div>
      @endif

      @if (in_array('tampilan', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Tampilan</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/edit-carosel" class="{{ 'edit-carosel' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-image"></i>
              <span class="mzt-sidebar-text">Carousel</span>
            </a>
          </li>
          <li class="mzt-sidebar-item">
            <a href="javascript:void(0)" onclick="toggleSubmenu('subTentang', this)">
              <i class="fas fa-info-circle"></i>
              <span class="mzt-sidebar-text">Tentang</span>
              <i class="fas fa-chevron-right mzt-sidebar-chevron {{ in_array(request()->segment(1), ['edit-info-pesantren','edit-info-mzt']) ? 'open':'' }}"></i>
            </a>
            <ul class="mzt-sidebar-sub {{ in_array(request()->segment(1), ['edit-info-pesantren','edit-info-mzt']) ? 'open':'' }}" id="subTentang">
              <li><a href="/edit-info-pesantren" class="{{ 'edit-info-pesantren' == request()->segment(1) ? 'active':'' }}">Tentang Pesantren</a></li>
              <li><a href="/edit-info-mzt" class="{{ 'edit-info-mzt' == request()->segment(1) ? 'active':'' }}">Tentang MZT</a></li>
            </ul>
          </li>
        </ul>
      </div>
      @endif

      @if (in_array('profil', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Profil</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/profil" class="{{ 'profil' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-user"></i>
              <span class="mzt-sidebar-text">Profil</span>
            </a>
          </li>
        </ul>
      </div>
      @endif

      @if (in_array('aktivitas_user', $status_akses))
      <div class="mzt-sidebar-group">
        <div class="mzt-sidebar-label">Log Anggota</div>
        <ul class="mzt-sidebar-menu">
          <li class="mzt-sidebar-item">
            <a href="/tabel-log-user" class="{{ 'tabel-log-user' == request()->segment(1) ? 'active':'' }}">
              <i class="fas fa-scroll"></i>
              <span class="mzt-sidebar-text">Log Anggota</span>
            </a>
          </li>
        </ul>
      </div>
      @endif

    </div>

    <div class="mzt-sidebar-footer">
      <div class="mzt-sidebar-footer-card mzt-sidebar-text">
        <div class="version"><i class="fas fa-star"></i> MZT APPS v1.0</div>
        <div class="desc">Maziltu Tholiban Organization System</div>
      </div>
      <div class="mzt-sidebar-footer-icon">
        <i class="fas fa-star"></i>
      </div>
    </div>
  </aside>

  <div class="mzt-sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

  <div class="mzt-main">
    <header class="mzt-topbar">
      <button class="mzt-topbar-toggle" onclick="toggleSidebar()" title="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </button>
      <div class="mzt-topbar-sep"></div>

      <div class="mzt-topbar-search">
        <i class="fas fa-search" style="font-size:14px"></i>
        <span>Search members, events...</span>
        <kbd>Ctrl K</kbd>
      </div>

      <div class="mzt-topbar-right">
        <button class="mzt-topbar-btn" onclick="toggleTheme()" title="Toggle theme" id="themeToggle">
          <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <button class="mzt-topbar-btn" title="Notifications">
          <i class="far fa-bell"></i>
        </button>
        <div style="position:relative">
          <button class="mzt-user-btn" onclick="toggleUserDropdown()">
            @if (File::exists(asset('storage/' . $foto_profil)) && !empty($foto_profil))
              <img src="/storage/{{$foto_profil}}" class="mzt-avatar" alt="avatar">
            @else
              <div class="mzt-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
            @endif
            <div class="mzt-user-info">
              <div class="name">{{ auth()->user()->name }}</div>
              <div class="role">Admin</div>
            </div>
          </button>
          <div class="mzt-user-dropdown" id="userDropdown">
            @if (in_array('profil', $status_akses))
            <a href="/profil"><i class="far fa-user"></i> Profil</a>
            @endif
            <div class="sep"></div>
            <a href="/logout" class="danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>

    <main class="mzt-content">
      <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            showCloseButton: true,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
      </script>

      @yield('konten')
    </main>

    <footer class="mzt-footer">
      Copyright &copy; 2023 &middot; Maziltu Tholiban
    </footer>
  </div>

</div>
</div>

<script src="/stisla/assets/js/popper.min.js"></script>
<script src="/stisla/assets/js/bootstrap.min.js"></script>
<script src="/stisla/assets/js/jquery.nicescroll.min.js"></script>
<script src="/stisla/assets/js/moment.min.js"></script>

<script>
  function toggleSidebar() {
    var sidebar = document.getElementById('mztSidebar');
    var isMobile = window.innerWidth <= 768;
    if (isMobile) {
      sidebar.classList.toggle('mobile-open');
      document.getElementById('sidebarOverlay').classList.toggle('show');
    } else {
      sidebar.classList.toggle('collapsed');
      localStorage.setItem('mzt_sidebar', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
    }
  }
  function closeMobileSidebar() {
    document.getElementById('mztSidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('show');
  }
  (function() {
    var state = localStorage.getItem('mzt_sidebar');
    if (state === 'collapsed') { document.getElementById('mztSidebar').classList.add('collapsed'); }
  })();
  function toggleSubmenu(id, el) {
    var sub = document.getElementById(id);
    var chevron = el.querySelector('.mzt-sidebar-chevron');
    sub.classList.toggle('open');
    if (chevron) chevron.classList.toggle('open');
  }
  function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme');
    var next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('mzt_theme', next);
    updateThemeIcon(next);
  }
  function updateThemeIcon(theme) {
    var icon = document.getElementById('themeIcon');
    if (icon) { icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'; }
  }
  (function() {
    var saved = localStorage.getItem('mzt_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);
  })();
  function toggleUserDropdown() { document.getElementById('userDropdown').classList.toggle('show'); }
  document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !e.target.closest('.mzt-user-btn') && !e.target.closest('.mzt-user-dropdown')) { dd.classList.remove('show'); }
  });
  window.addEventListener('resize', function() { if (window.innerWidth > 768) closeMobileSidebar(); });
</script>

</body>
</html>