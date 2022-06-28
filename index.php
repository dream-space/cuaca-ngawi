<?php

function parse($url) {
    $fileContents= file_get_contents($url);
    $fileContents = str_replace(array("\n", "\r", "\t"), '', $fileContents);
    $fileContents = trim(str_replace('"', "'", $fileContents));
    $simpleXml = simplexml_load_string($fileContents);
    $json = json_encode($simpleXml);
    return $json;
}

function getWeatherName($val){
    /*
    0 : Cerah / Clear Skies
    1 : Cerah Berawan / Partly Cloudy
    2 : Cerah Berawan / Partly Cloudy
    3 : Berawan / Mostly Cloudy
    4 : Berawan Tebal / Overcast
    5 : Udara Kabur / Haze
    10 : Asap / Smoke
    45 : Kabut / Fog
    60 : Hujan Ringan / Light Rain
    61 : Hujan Sedang / Rain
    63 : Hujan Lebat / Heavy Rain
    80 : Hujan Lokal / Isolated Shower
    95 : Hujan Petir / Severe Thunderstorm
    97 : Hujan Petir / Severe Thunderstorm
    */

    if($val == "0"){
        return "Cerah";
    } else if($val == "1" || $val == "2"){
        return "Cerah Berawan";
    } else if($val == "3"){
        return "Berawan";
    } else if($val == "4"){
        return "Berawan Tebal";
    } else if($val == "5"){
        return "Udara Kabur";
    } else if($val == "10"){
        return "Asap";
    } else if($val == "45"){
        return "Kabut";
    } else if($val == "60"){
        return "Hujan Ringan";
    } else if($val == "61"){
        return "Hujan Sedang";
    } else if($val == "63"){
        return "Hujan Lebat";
    } else if($val == "80"){
        return "Hujan Lokal";
    } else if($val == "95" || $val == "97"){
        return "Hujan Petir";
    }
    return "";
}

// Cleans up ugly JSON data by removing @attributes tag
function cleanup_json ($ugly_json) {
    if (is_object($ugly_json)) {
       $nice_json = new stdClass();
       foreach ($ugly_json as $attr => $value) {
          if ($attr == '@attributes') {
             foreach ($value as $xattr => $xvalue) {
               $nice_json->$xattr = $xvalue;
             }
          } else {
             $nice_json->$attr = cleanup_json($value);
          }
       }
       return $nice_json;
    } else if (is_array($ugly_json)) {
       $nice_json = array();
       foreach ($ugly_json as $n => $e) {
         $nice_json[$n] = cleanup_json($e);
       }
       return $nice_json;
    } else {
       return $ugly_json;
    }
}

function get_now_data($data, $current_date, $current_hour) {
    $current_hour = (int) $current_hour;
    if($current_hour < 6){
        $current_hour = "00";
    } else if($current_hour >= 6 && $current_hour < 12){
        $current_hour = "06";
    } else if($current_hour >= 12 && $current_hour < 18){
        $current_hour = "12";
    } else if($current_hour >= 18){
        $current_hour = "18";
    }
    $date_hour = $current_date . $current_hour . "00";
    $result = new stdClass();
    foreach($data as $r){
        if(str_ends_with($r->datetime, $date_hour)){
            $result = $r;
            break;
        }
    }
    return $result;
}

// From URL to get webpage contents.
$url = "https://data.bmkg.go.id/DataMKG/MEWS/DigitalForecast/DigitalForecast-JawaTimur.xml";
$result = cleanup_json(json_decode(parse($url))->forecast->area);
$ngawi = new stdClass();
$data = new stdClass();
$data->pressure = null;

date_default_timezone_set('Asia/Jakarta');
$current_hour = date('H');
$current_date = date('d');

$weather_forecast = null;
$temperature_forecast = null;

foreach($result as $r){
    if($r->description == "Ngawi"){
        $ngawi = $r;
        break;
    }
}
// $data->original = $ngawi;
//
$parameter = $ngawi->parameter;
foreach($parameter as $p){
    // weather
    if($p->id == "weather"){
        $weather_timerange = get_now_data($p->timerange, $current_date, $current_hour);
        $data->weather_date = $weather_timerange->datetime;
        $data->weather = getWeatherName($weather_timerange->value);
        $data->weather_code = $weather_timerange->value;
        $weather_forecast = array_slice($p->timerange, 4);
    }

    // temperature
    if($p->id == "t"){
        $temperature_timerange = get_now_data($p->timerange, $current_date, $current_hour);
        $data->temperature = $temperature_timerange->value[0];
        $temperature_forecast = array_slice($p->timerange, 4);
    }

    //humidity
    if($p->id == "hu"){
        $humidity_timerange = get_now_data($p->timerange, $current_date, $current_hour);
        $data->humidity = $humidity_timerange->value;
    }
    
    //wind_speed
    if($p->id == "ws"){
        $wind_timerange = get_now_data($p->timerange, $current_date, $current_hour);
        $data->wind_speed = $wind_timerange->value[2];
    }
}

//tmax
if($weather_forecast != null && $temperature_forecast != null){
    $data->forecast = array();
    for($i = 0; $i < 8; $i++){
        $frc = new stdClass();
        $frc->weather = getWeatherName($weather_forecast[$i]->value);
        $frc->weather_code = $weather_forecast[$i]->value;
        $frc->day = substr($weather_forecast[$i]->datetime,0,10);
        $frc->temp = $temperature_forecast[$i]->value[0];
        $data->forecast[] = $frc;
    }
}
header("Content-Type:application/json");
echo json_encode($data);;

?>
