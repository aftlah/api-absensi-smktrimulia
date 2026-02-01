<?php

function hitungJarak($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    return 2 * $earthRadius * asin(sqrt($a));
}



$latSekolah = -6.2383033804245684;
$lonSekolah = 106.74789798984251;

$latSiswa = -6.23842466007809;
$lonSiswa = 106.74762886356702;

$jarak = hitungJarak($latSiswa, $lonSiswa, $latSekolah, $lonSekolah);

echo "Jarak siswa ke sekolah: " . $jarak . " meter" . PHP_EOL;

