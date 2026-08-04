<?php
namespace App\Helpers;
 
use Illuminate\Support\Facades\DB;
use Request;
use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Support\Facades\Http;
use App\Models\Activitas_log;
 
class DataRangeHelper {

    public static function dateRange($date) {
        $regex = '/\s*-\s*/';
        $dates = preg_split($regex, $date);
        
        $start_date = trim($dates[0]);
        $end_date = trim($dates[1]);
        
        $start_date = DateTime::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
        $end_date = DateTime::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');

        $begin = new DateTime($start_date);
        $end = new DateTime( $end_date);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end->add(new DateInterval('P1D')));
        $data_array_tanggal = [];

        foreach ($period as $dt) {
            $p = [$dt->format("Y-m-d\n")];
            array_push($data_array_tanggal,$p);
        }
  
        return $data_array_tanggal;
    }

    public static function activitas_log($subject){

        Activitas_log::insert([
            'subject' => $subject,
            'url' =>  Request::fullUrl(),
            'method' =>  Request::method(),
            'agent' =>  Request::header('user-agent'),
            'user_id' =>  auth()->check() ? auth()->user()->id : 1,
        ]);

    }

    public static function sendWa($telepon, $message = 'tidak ada data')
    {

        $userkey = '7524aab345e6';
        $passkey = '366e7cc78ff2b469146c583b';
        $url = 'https://console.zenziva.net/wareguler/api/sendWA/';
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        curl_setopt($curlHandle, CURLOPT_HEADER, 0);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT,30);
        curl_setopt($curlHandle, CURLOPT_POST, 1);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array(
            'userkey' => $userkey,
            'passkey' => $passkey,
            'to' => $telepon,
            'message' => $message
        ));
        $results = json_decode(curl_exec($curlHandle), true);
        curl_close($curlHandle);

        return  $results;
        
    }

}