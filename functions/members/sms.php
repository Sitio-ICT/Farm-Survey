<?php
// SMS delivery for one-time codes. Drivers: 'log' (development) and 'termii'.

/**
 * Sends $text to $phone (E.164). Returns true if the provider accepted it.
 */
function sms_send($phone, $text)
{
    $cfg = appConfig()['sms'] ?? ['driver' => 'log'];
    switch ($cfg['driver'] ?? 'log') {
        case 'termii':
            return sms_send_termii($cfg, $phone, $text);
        case 'log':
            // Writes codes in clear text, so it is refused outside development.
            if (!is_dev()) {
                error_log('sms.driver "log" is only allowed when app_env is development.');
                return false;
            }
            $dir = __DIR__ . '/../../storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            $masked = substr($phone, 0, 7) . '****' . substr($phone, -3);
            file_put_contents($dir . '/sms.log', date('c') . " to $masked: $text\n", FILE_APPEND | LOCK_EX);
            return true;
        default:
            error_log('Unknown sms.driver ' . $cfg['driver']);
            return false;
    }
}

function sms_send_termii(array $cfg, $phone, $text)
{
    $payload = [
        'api_key' => $cfg['api_key'],
        'to' => ltrim($phone, '+'),     // Termii expects 234XXXXXXXXXX
        'from' => $cfg['sender_id'],
        'sms' => $text,
        'type' => 'plain',
        'channel' => $cfg['channel'] ?? 'dnd',
    ];
    $ch = curl_init(rtrim($cfg['base_url'] ?? 'https://api.ng.termii.com', '/') . '/api/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($status !== 200) {
        error_log("Termii SMS failed (HTTP $status): " . ($err ?: substr((string) $body, 0, 300)));
        return false;
    }
    return true;
}
