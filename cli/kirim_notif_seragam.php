#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php'); // tetap load Moodle (untuk mtrace)
date_default_timezone_set('Asia/Makassar'); // WITA

/* =================== Wablas Config (langsung di file) =================== */
$WABLAS_TOKEN  = '4L94T0YIsPSOmB1W3Q8Gzlj637DMLigCMrucozQjwVtvAd1JnkqulZT';
$WABLAS_SECRET = 'zNYpFdMZ';
$WABLAS_URL    = 'https://sby.wablas.com/api/v2/send-message';

/* ============================ CLI args ================================== */
// --mode=auto|before|after
// --target=YYYY-MM-DD
// --debug
// --group=JID_OR_ID (override tujuan)
// --jid=auto|raw|gus|both  (cara bentuk phone grup)
$mode   = 'auto';
$targetOverride = null;
$debug  = false;
$groupOverride = null;
$jidmode = 'auto';

foreach ($argv as $arg) {
    if (preg_match('/^--mode=(.+)$/', $arg, $m))        $mode = strtolower($m[1]);
    elseif (preg_match('/^--target=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) $targetOverride = $m[1];
    elseif ($arg === '--debug')                          $debug = true;
    elseif (preg_match('/^--group=(.+)$/', $arg, $m))    $groupOverride = trim($m[1]);
    elseif (preg_match('/^--jid=(auto|raw|gus|both)$/i', $arg, $m)) $jidmode = strtolower($m[1]);
}

/* ======================= Tentukan target (H) ============================ */
$now = new DateTime('now');
if ($targetOverride) {
    $target = DateTime::createFromFormat('Y-m-d', $targetOverride, new DateTimeZone('Asia/Makassar'));
    if (!$target) { mtrace("[ERR] Format --target harus YYYY-MM-DD"); exit(1); }
} else {
    if     ($mode === 'before') $target = (clone $now)->modify('+1 day'); // besok
    elseif ($mode === 'after')  $target = (clone $now)->modify('+2 day'); // lusa
    else                        $target = ((int)$now->format('H') < 20) ? (clone $now)->modify('+1 day') : (clone $now)->modify('+2 day');
}

/* ============================ Helpers =================================== */
function indo_tanggal(DateTime $dt): string {
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $hari[(int)$dt->format('w')].", ".$dt->format('j')." ".$bulan[(int)$dt->format('n')]." ".$dt->format('Y');
}
function week_of_month(DateTime $dt): int {
    return intdiv(((int)$dt->format('j')) - 1, 7) + 1; // 1..5
}
function label_waktu(DateTime $now, DateTime $target): string {
    $diffDays = (int)$now->diff($target)->format('%a');
    if ($diffDays === 1) return 'besok';
    if ($diffDays === 2) return 'lusa';
    return 'pada';
}

/* ===================== Komponen tanggal & aturan ======================== */
$dowN    = (int)$target->format('N');  // 1=Sen..7=Ming
$dayNum  = (int)$target->format('j');  // 1..31
$wom     = week_of_month($target);     // 1..5
$tanggal = indo_tanggal($target);
$label   = label_waktu($now, $target);

/* ====== Group ID Default (boleh override lewat --group) ====== */
$GROUP_BASE = '120363196604363056'; // BARU Guru (pakai JID lengkap kalau punya)
$GROUP_ID   = $groupOverride ?: $GROUP_BASE;

if ($debug) {
    mtrace("DEBUG now     : ".$now->format('Y-m-d H:i:s').' WITA');
    mtrace("DEBUG target  : ".$target->format('Y-m-d').' ('.$target->format('l').')');
    mtrace("DEBUG week    : $wom  dayNum=$dayNum  dowN=$dowN");
    mtrace("DEBUG label   : $label");
    mtrace("DEBUG group   : $GROUP_ID");
    mtrace("DEBUG jidmode : $jidmode");
}

/* ========================== Bangun pesan ================================ */
$pesan = [];
// 1) Rabu minggu pertama: PDH abu-abu
if ($dowN === 3 && $wom === 1) {
    $pesan[] = "Pengingat seragam $label $tanggal: Rabu Minggu Pertama — *Baju PDL Bungas*.";
}
// 2) KORPRI tiap tgl 17 bila Sen–Jum
if ($dayNum === 17 && $dowN >= 1 && $dowN <= 5) {
    $pesan[] = "Pengingat seragam $label $tanggal: *Baju KORPRI*";
}
// 3a) eDialog SKP tgl 1
if ($dayNum == 1) {
    $pesan[] = "Pengingat $label $tanggal: untuk *mengisi e-Dialog* (periode tanggal 1–5).";
}

// 3b) eDialog SKP tgl 5
if ($dayNum == 5) {
    $pesan[] = "Pengingat $label $tanggal: jangan lupa *mengisi eDialog* (periode tanggal 1–5). Abaikan bila sudah";
}
// 4) Kamis: Sasirangan (M1 hitam, M2 biru motif ketupat, M3 hijau tua, M4 ungu)
if ($dowN === 4) {
    $warna = [1=>'warna hitam', 2=>'warna biru motif ketupat', 3=>'warna hijau tua', 4=>'warna ungu', 5=>'warna tidak ditentukan'];
    if (isset($warna[$wom])) {
        $pesan[] = "Pengingat seragam $label $tanggal: Kamis Minggu ke-$wom — *Sasirangan {$warna[$wom]}*.";
    }
}

if (empty($pesan)) {
    mtrace("[INFO] Tidak ada notifikasi untuk $label ($tanggal).");
    if (!$debug) mtrace("      (Gunakan --debug, atau --mode=before / --target=YYYY-MM-DD)");
    exit(0);
}

$header = "📣 *Pengumuman Pengingat*\n";
$footer = "\n_Dikirim otomatis melalui server SiM_.Terima kasih 🙏";
$body   = implode("\n", array_map(fn($p) => "• ".$p, $pesan));
$message = $header.$body.$footer;

/* ===================== Kirim ke Wablas (Group) ========================== */
// sesuai dokumentasi: phone = Group ID/JID, dan isGroup = 'true'
function wablas_send_group($url, $token, $secret, $groupjid, $message, $debug = false) {
    $payload = [
        "data" => [[
            "phone"    => $groupjid,
            "message"  => $message,
            "isGroup"  => "true",   // HARUS string "true" sesuai contoh dok
        ]]
    ];
    $auth = $token . '.' . $secret;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $auth",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($debug) {
        mtrace("DEBUG req.body: ".substr(json_encode($payload), 0, 400));
        if ($errno) mtrace("DEBUG cURL   : ($errno) $err");
        mtrace("DEBUG resp    : ".substr((string)$resp, 0, 400));
    }

    if ($errno === 0 && $resp) {
        $j = json_decode($resp, true);
        if (is_array($j)) {
            if ((isset($j['code']) && (int)$j['code'] === 200) ||
                (isset($j['success']) && $j['success'] === true) ||
                (isset($j['status']) && ($j['status'] === true || $j['status'] === 'success'))) {
                return true;
            }
        }
        if (stripos((string)$resp, 'success') !== false) return true;
    }
    return false;
}

/* ============ Siapkan kandidat JID sesuai --jid ============ */
$candidates = [];
switch ($jidmode) {
    case 'raw':
        $candidates = [$GROUP_ID];
        break;
    case 'gus':
        $candidates = [str_contains($GROUP_ID, '@g.us') ? $GROUP_ID : $GROUP_ID.'@g.us'];
        break;
    case 'both':
        $candidates = array_values(array_unique([
            $GROUP_ID,
            str_contains($GROUP_ID, '@g.us') ? $GROUP_ID : $GROUP_ID.'@g.us'
        ]));
        break;
    case 'auto':
    default:
        $candidates = [str_contains($GROUP_ID, '@g.us') ? $GROUP_ID : $GROUP_ID.'@g.us'];
        break;
}

if ($debug) mtrace("DEBUG try phones: ".implode(', ', $candidates));

$sent = false;
foreach ($candidates as $jid) {
    if (wablas_send_group($WABLAS_URL, $WABLAS_TOKEN, $WABLAS_SECRET, $jid, $message, $debug)) {
        mtrace("[OK] Notifikasi terkirim ke grup ($jid) — target: $tanggal, mode=$mode".($targetOverride ? ", override=$targetOverride" : "").".");
        $sent = true;
        break;
    } else {
        mtrace("[WARN] Gagal kirim ke: $jid, coba kandidat berikutnya…");
    }
}

if (!$sent) {
    mtrace("[ERR] Semua percobaan gagal.");
    mtrace("     • Pastikan Group JID benar (format 120…-……@g.us)");
    mtrace("     • Device Wablas join grup & diizinkan");
    mtrace("     • Token & secret benar (Authorization: token.secret)");
    mtrace("     • Bisa coba: --group='120…-……@g.us' --jid=raw --debug");
}
