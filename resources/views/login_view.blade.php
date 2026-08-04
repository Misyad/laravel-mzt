<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>MZT APPS - Login</title>
  <link href="/assets/logo-pondok.jpg" rel="icon">
  <link rel="stylesheet" href="/stisla/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/mzt-orgos.css">
</head>
<body class="mzt-orgos">
  <div class="mzt-login-wrap">
    <div class="mzt-login-card">
      <div class="mzt-login-logo">
        <img src="/assets/logo-pondok.jpg" alt="logo">
      </div>
      <h2>Login</h2>
      <form method="POST" action="/login-aksi">
        @csrf
        <div class="form-group">
          <label for="id_anggota">ID Anggota</label>
          <input id="id_anggota" type="number" class="form-control" name="id_anggota" tabindex="1" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" type="password" class="form-control" name="password" tabindex="2" required>
        </div>
        <button type="submit" class="btn btn-primary" tabindex="4">Login</button>
      </form>
    </div>
  </div>
  <script src="/stisla/assets/jquery.min.js"></script>
  <script src="/stisla/assets/js/bootstrap.min.js"></script>
</body>
</html>
