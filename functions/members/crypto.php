<?php
// Identity vault: encryption at rest for selfies and ID images, and keyed hashes
// for ID numbers. Keys come from functions/config.php ('vault'), never the database.

function vault_config()
{
    $cfg = appConfig()['vault'] ?? [];
    foreach (['vault_key', 'hmac_key'] as $k) {
        $raw = base64_decode($cfg[$k] ?? '', true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException("functions/config.php: vault.$k must be 32 random bytes, base64-encoded.");
        }
        $cfg[$k . '_raw'] = $raw;
    }
    return $cfg;
}

/** Keyed hash of an identifier (ID number, phone). Same input + key = same output. */
function vault_hmac($value)
{
    return hash_hmac('sha256', strtoupper(preg_replace('/\s+/', '', (string) $value)), vault_config()['hmac_key_raw']);
}

function vault_encrypt($plain)
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($plain, $nonce, vault_config()['vault_key_raw']);
}

function vault_decrypt($blob)
{
    $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, vault_config()['vault_key_raw']);
    if ($plain === false) {
        throw new RuntimeException('Vault file could not be decrypted (wrong key or damaged file).');
    }
    return $plain;
}

function vault_dir()
{
    $dir = rtrim(vault_config()['storage_path'], '/');
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
        throw new RuntimeException("Cannot create vault directory $dir");
    }
    return $dir;
}

/** Encrypts $bytes to a new file and returns its name (no path, no extension hint). */
function vault_store($bytes)
{
    $name = bin2hex(random_bytes(20)) . '.bin';
    $path = vault_dir() . '/' . $name;
    if (file_put_contents($path, vault_encrypt($bytes), LOCK_EX) === false) {
        throw new RuntimeException('Could not write vault file');
    }
    chmod($path, 0600);
    return $name;
}

function vault_read($name)
{
    if (!preg_match('/^[a-f0-9]{40}\.bin$/', (string) $name)) {
        throw new InvalidArgumentException('Bad vault file name');
    }
    $blob = file_get_contents(vault_dir() . '/' . $name);
    if ($blob === false) {
        throw new RuntimeException('Vault file missing');
    }
    return vault_decrypt($blob);
}

/**
 * Checks an uploaded or captured image and re-encodes it as JPEG with GD, which
 * drops EXIF (GPS, device) and anything appended to the file. Longest side is
 * capped at $maxSide. Returns JPEG bytes or null if it isn't a usable image.
 */
function sanitize_image($bytes, $maxSide = 1024)
{
    if (!is_string($bytes) || strlen($bytes) < 1000 || strlen($bytes) > 6 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 200) {
        return null;
    }
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return null;
    }
    $scale = min(1, $maxSide / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagejpeg($dst, null, 85);
    $out = ob_get_clean();
    imagedestroy($src);
    imagedestroy($dst);
    return $out;
}
