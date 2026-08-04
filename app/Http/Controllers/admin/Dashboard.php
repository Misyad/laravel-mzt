<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\DataUser;
use DateTime;
use Illuminate\Support\Facades\DB;
use App\Models\Event_status;
use DataPicker;

class Dashboard extends Controller
{
    function index()
    {
        $event_selesai =  Event_status::where('is_active', '1')
        ->whereRaw("status COLLATE utf8mb4_unicode_ci = 'Complete'")
        ->count();
        $event_mendatang = Event_status::where('is_active','1')
                        ->whereRaw("status COLLATE utf8mb4_unicode_ci = 'Upcomming'")
                        ->count();
        $event = Event::where('is_active','1')->count();
        $event_all = Event_status::where('is_active','1')->orderBy(DB::raw("FIELD(status COLLATE utf8mb4_unicode_ci, 'Ongoing', 'Upcomming', 'Complate')"))->get();
        $total_anggota = DataUser::where('is_active','1')->count();
        \DataPicker::activitas_log('membuka dahboard');
        return view('admin.dashboard',['event_all' => $event_all,'total_anggota' => $total_anggota, 'event' => $event,'event_selesai' => $event_selesai,'event_mendatang' => $event_mendatang]);
    }

    function getCalender()
    {

        $data = Event_status::where('is_active','1')->get();
        $data_array = [];

        foreach($data as $val){

            $regex = '/\s*-\s*/';
            $dates = preg_split($regex,  $val->tanggal);

            $start_date = trim($dates[0]);
            $end_date = trim($dates[1]);

            $start_date = DateTime::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
            $end_date = DateTime::createFromFormat('d/m/Y', $end_date)->modify('+1 day')->format('Y-m-d');
            // $randomColor = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            $warna  = "";
            if( $val->status == "Ongoing"){
                $warna = "#3abaf4";
            }elseif($val->status == "Complate"){
                $warna = "#47c363";

            }elseif($val->status == "Upcomming"){
                $warna = "#ffa426";
            }
            $a = [
                'title' => $val->judul_event,
                'start' => $start_date,
                'end' => $end_date,
                'backgroundColor' =>  $warna ,
                'borderColor' =>  $warna,
                'textColor' =>  '#fff',
            ];
            array_push($data_array,$a);
        }


        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data'    => $data_array ,

        ],200);
    }


}
