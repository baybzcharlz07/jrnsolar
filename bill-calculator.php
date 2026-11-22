<?php
// Shared PHP calculation: public inputs only; no customer data is stored.
function jrn_bill_projection(array $query): array {
    $read = static function(string $key, float $default, float $min, float $max) use ($query): float {
        $v = $query[$key] ?? null;
        return is_scalar($v) && is_numeric($v) && is_finite((float)$v) ? max($min,min($max,(float)$v)) : $default;
    };
    $bill=round($read('monthly_bill',5000,500,50000));
    $utility=$read('utility_rate',12,5,30);
    $rate=round($read('annual_increase',0,0,10),1);
    $total=0;
    for($year=0;$year<10;$year++) { $total += $bill*12*pow(1+$rate/100,$year); }
    return ['monthly'=>$bill,'annual'=>$bill*12,'ten_year'=>round($total),'rate'=>$rate,'annual_savings'=>round($bill*12*0.85),'monthly_savings'=>round($bill*0.85),'remaining_monthly'=>round($bill*0.15),'utility_rate'=>$utility,'usage'=>round($bill/$utility)];
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(jrn_bill_projection($_GET));
}
