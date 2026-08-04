<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Print Id Card</title>
    <script src="/stisla/assets/jquery.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: 0;
            
        }
        html {
            width: 122mm;
            height: 94mm;
        }
        @media print {
        /* Ganti .cetak-saja dengan kelas atau ID elemen yang ingin Anda cetak */
        .cetak-saja {
            display: block;
        }

        /* Ganti .sembunyikan dengan kelas atau ID elemen yang ingin Anda sembunyikan saat mencetak */
        .sembunyikan {
            display: none;
        }
}


    </style>
</head>
<body>
    <div style="height: 122mm; width: 94mm; position: relative; " class="cetak-saja">
        <img style="height: 122mm; width: 94mm;" src="{{ asset('storage/' . $templateIdCard->path) }}" alt="{{ $templateIdCard->nama_gambar }}">
        <img
            @if (!empty($dataUser['photo']) && !File::exists(asset('storage/' . $dataUser['photo'])))
                style="width: 31mm; height: 43mm; object-fit: cover; position: absolute; top: 182px; left: 120px;"
                src="{{ asset('storage/' . $dataUser['photo']) }}"
            @else
                style="width: 31mm; height: 43mm; object-fit: cover; position: absolute; top: 182px; left: 120px;"
                src="{{ asset('/assets/avatar-1.png') }}"
            @endif
            alt="Photo"
        >
        <div style="position: absolute; top: 383px;width: 342px;text-align: center; font-family: 'Cinzel', serif;" >
            <p class="text-center">{{$dataUser['name']}}</p>
        </div>
        <div style="position: absolute; top: 360px ;width: 342px;text-align: center; font-family: 'Cinzel', serif;" class="text-center" >
                <p >{{$dataUser['niqobah']}}</p>
        </div>
            <p style="top: 0; right: 0; position: absolute; font-weight: 600; color: #c5c5c5;" >{{$dataUser['idTransaction']}}</h1>
    </div>
</body>
</html>

<script>
        $(document).ready(function() {
            window.print();
          });
</script>