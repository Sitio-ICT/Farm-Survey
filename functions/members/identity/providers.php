<?php
// Identity providers: look up a NIN, face-match a selfie against the registry photo.
// Swap providers in functions/config.php ('identity.provider') without touching pages.

/**
 * Result of one provider call.
 *   status: 'found' | 'not_found' | 'error'
 *   record: ['first_name','middle_name','last_name','date_of_birth'] from the registry (found only)
 *   selfie_confidence: 0..100 face-match score, or null if the provider gave none
 *   reference: provider's request reference, if any
 *   error: short reason for 'error' (logged, not shown to members)
 */
final class IdentityResult
{
    public $status;
    public $record = [];
    public $selfieConfidence = null;
    public $reference = null;
    public $error = null;

    public static function found(array $record, $selfieConfidence, $reference = null)
    {
        $r = new self();
        $r->status = 'found';
        $r->record = $record;
        $r->selfieConfidence = $selfieConfidence === null ? null : (float) $selfieConfidence;
        $r->reference = $reference;
        return $r;
    }

    public static function notFound()
    {
        $r = new self();
        $r->status = 'not_found';
        return $r;
    }

    public static function error($why)
    {
        $r = new self();
        $r->status = 'error';
        $r->error = $why;
        return $r;
    }
}

interface IdentityProvider
{
    /** Short name stored with each verification row. */
    public function name();

    /**
     * Verify a NIN and match the selfie (raw JPEG bytes) against the registry photo.
     * $declared is the member's own declared name and date of birth; only the
     * sandbox uses it, to produce matching test records.
     */
    public function verifyNin($nin, $selfieJpeg, array $declared);
}

/**
 * Dojah: POST /api/v1/kyc/nin/verify with the NIN and a base64 selfie.
 * Auth headers are the app's secret key (no "Bearer") and AppId.
 * Sandbox base URL https://sandbox.dojah.io, production https://api.dojah.io.
 */
final class DojahProvider implements IdentityProvider
{
    private $cfg;

    public function __construct(array $cfg)
    {
        foreach (['base_url', 'app_id', 'secret_key'] as $k) {
            if (empty($cfg[$k])) {
                throw new RuntimeException("functions/config.php: identity.$k is required for the Dojah provider.");
            }
        }
        $this->cfg = $cfg;
    }

    public function name()
    {
        return 'dojah';
    }

    public function verifyNin($nin, $selfieJpeg, array $declared)
    {
        $payload = [
            'nin' => $nin,
            'selfie_image' => base64_encode($selfieJpeg), // without the data:image/...;base64, prefix
            'threshold' => (int) ($this->cfg['selfie_threshold'] ?? 80),
        ];
        $ch = curl_init(rtrim($this->cfg['base_url'], '/') . '/api/v1/kyc/nin/verify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'AppId: ' . $this->cfg['app_id'],
                'Authorization: ' . $this->cfg['secret_key'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return IdentityResult::error('network: ' . $curlErr);
        }
        if ($status === 404) {
            return IdentityResult::notFound();
        }
        if ($status !== 200) {
            // 400 bad input, 401 keys, 402 wallet empty, 424 NIMC unavailable, 429 rate limited
            return IdentityResult::error("http $status: " . substr($body, 0, 200));
        }
        $json = json_decode($body, true);
        $entity = $json['entity'] ?? null;
        if (!is_array($entity)) {
            return IdentityResult::error('unexpected response shape');
        }
        return IdentityResult::found(
            [
                'first_name' => $entity['first_name'] ?? '',
                'middle_name' => $entity['middle_name'] ?? '',
                'last_name' => $entity['last_name'] ?? '',
                'date_of_birth' => $entity['date_of_birth'] ?? '',
            ],
            $entity['selfie_verification']['confidence_value'] ?? null,
            $json['reference_id'] ?? null
        );
    }
}

/**
 * Offline test provider. Behaviour depends on the NIN's last digit:
 *   0, 4-9  registry matches what the member declared, selfie 96   -> verified
 *   1       registry has a different surname                       -> manual review
 *   2       no record                                              -> rejected
 *   3       selfie scores 42                                       -> rejected
 * 70123456789 (Dojah's documented sandbox NIN) returns John Doe Musa, 1982-01-01.
 */
final class SandboxProvider implements IdentityProvider
{
    public function name()
    {
        return 'sandbox';
    }

    public function verifyNin($nin, $selfieJpeg, array $declared)
    {
        if ($nin === '70123456789') {
            return IdentityResult::found(
                ['first_name' => 'JOHN', 'middle_name' => 'DOE', 'last_name' => 'MUSA', 'date_of_birth' => '1982-01-01'],
                96.0,
                'sandbox-' . bin2hex(random_bytes(4))
            );
        }
        $record = [
            'first_name' => strtoupper($declared['first_name']),
            'middle_name' => strtoupper($declared['middle_name'] ?? ''),
            'last_name' => strtoupper($declared['last_name']),
            'date_of_birth' => date('d-M-Y', strtotime($declared['date_of_birth'])),
        ];
        switch (substr($nin, -1)) {
            case '1':
                $record['last_name'] = 'OKONKWO-ADEYEMI';
                return IdentityResult::found($record, 95.0, 'sandbox-' . bin2hex(random_bytes(4)));
            case '2':
                return IdentityResult::notFound();
            case '3':
                return IdentityResult::found($record, 42.0, 'sandbox-' . bin2hex(random_bytes(4)));
            default:
                return IdentityResult::found($record, 96.0, 'sandbox-' . bin2hex(random_bytes(4)));
        }
    }
}

function identity_provider()
{
    $cfg = appConfig()['identity'] ?? ['provider' => 'sandbox'];
    switch ($cfg['provider'] ?? 'sandbox') {
        case 'dojah':
            return new DojahProvider($cfg);
        case 'sandbox':
            if (!is_dev()) {
                throw new RuntimeException('The sandbox identity provider is only allowed when app_env is development.');
            }
            return new SandboxProvider();
        default:
            throw new RuntimeException('Unknown identity.provider ' . $cfg['provider']);
    }
}
