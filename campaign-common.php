<?php
declare(strict_types=1);
if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'campaign-common.php') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('jrn_campaign');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
session_start();
header('Cache-Control: private, no-store, no-cache, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $url): void { header('Location: '.$url, true, 303); exit; }
function private_dir(): string {
    $root = realpath(__DIR__);
    $dir = dirname($root).'/.jrn-solar-private';
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) { throw new RuntimeException('Storage unavailable'); }
    $resolved = realpath($dir);
    if (!$resolved || strpos($resolved.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR) === 0) { throw new RuntimeException('Storage must be private'); }
    return $resolved;
}
function store_lead(array $lead): string {
    $dir = private_dir();
    $file = $dir.'/campaign-leads.jsonl';
    $handle = fopen($file, 'c+b');
    if (!$handle || !flock($handle, LOCK_EX)) { throw new RuntimeException('Unable to lock storage'); }
    try {
        // One lock covers duplicate/rate checks and the append, including concurrent requests.
        $ipHash = hash('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? '').$dir);
        $now = time(); $recent = 0;
        rewind($handle);
        while (($line = fgets($handle)) !== false) {
            $prior = json_decode($line,true);
            if (!is_array($prior)) { continue; }
            if (($prior['submission_id'] ?? '') === $lead['submission_id']) { return (string)$prior['reference']; }
            if (($prior['rate_key'] ?? '') === $ipHash && (int)($prior['timestamp'] ?? 0) > $now-900) { $recent++; }
        }
        if ($recent >= 5) { throw new RuntimeException('RATE_LIMIT'); }
        $lead['rate_key']=$ipHash; $lead['timestamp']=$now;
        $line=json_encode($lead,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n";
        fseek($handle,0,SEEK_END); $start=ftell($handle); $written=0;
        while ($written < strlen($line)) {
            $n=fwrite($handle,substr($line,$written));
            if ($n === false || $n === 0) { ftruncate($handle,$start); throw new RuntimeException('Write failed'); }
            $written+=$n;
        }
        if (!fflush($handle)) { ftruncate($handle,$start); throw new RuntimeException('Flush failed'); }
        chmod($file,0600);
        return $lead['reference'];
    } finally { flock($handle,LOCK_UN); fclose($handle); }
}
