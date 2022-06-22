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

// From URL to get webpage contents.
$url = "https://data.bmkg.go.id/DataMKG/MEWS/DigitalForecast/DigitalForecast-JawaTimur.xml";
$result = cleanup_json(json_decode(parse($url))->forecast->area);
$ngawi = new stdClass();
$data = new stdClass();
$data->pressure = null;
foreach($result as $r){
    if($r->description == "Ngawi"){
        $ngawi = $r;
        break;
    }
}
//
$parameter = $ngawi->parameter;
foreach($parameter as $p){
    // weather
    if($p->id == "weather"){
        $data->weather = getWeatherName($p->timerange[0]->value);
        $data->weather_code = $p->timerange[0]->value;
    }

    // temperature
    if($p->id == "t"){
        $data->temperature = $p->timerange[0]->value[0];
    }

    //humidity
    if($p->id == "hu"){
        $data->humidity = $p->timerange[0]->value;
    }
    
    //wind_speed
    if($p->id == "ws"){
        $data->wind_speed = $p->timerange[0]->value[2];
    }
}

header("Content-Type:application/json");
echo json_encode($data);;

?>
