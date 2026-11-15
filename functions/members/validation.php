<?php
// Input normalisation and the name/date matching used by identity checks.

/** Nigerian mobile number to E.164 (+234XXXXXXXXXX), or null if it isn't one. */
function normalize_ng_phone($input)
{
    $digits = preg_replace('/\D/', '', (string) $input);
    if (preg_match('/^0([789][01]\d{8})$/', $digits, $m)) {
        return '+234' . $m[1];
    }
    if (preg_match('/^234([789][01]\d{8})$/', $digits, $m)) {
        return '+234' . $m[1];
    }
    return null;
}

function clean_name($value)
{
    $value = trim(preg_replace('/\s+/', ' ', (string) $value));
    return preg_match("/^[\p{L}][\p{L}'\- ]{0,59}$/u", $value) ? $value : null;
}

/** Upper-case letters only, accents removed: "Adébáyọ̀ O'Neil" -> "ADEBAYOONEIL". */
function name_key($value)
{
    $value = (string) $value;
    if (class_exists('Normalizer')) {
        $value = Normalizer::normalize($value, Normalizer::FORM_D);
    }
    $value = preg_replace('/\p{Mn}/u', '', $value);
    return strtoupper(preg_replace('/[^A-Za-z]/', '', $value));
}

function similarity($a, $b)
{
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 1.0;
    }
    $longest = max(strlen($a), strlen($b));
    return 1 - levenshtein($a, $b) / $longest;
}

/**
 * How well the declared first and last names match the registry record (0..1).
 * Each declared name is compared with every registry name, so swapped order or
 * a middle name used as first name still matches; middle names are not required.
 */
function name_match_score(array $declared, array $registry)
{
    $regNames = array_values(array_filter(array_map('name_key', [
        $registry['first_name'] ?? '', $registry['middle_name'] ?? '', $registry['last_name'] ?? '',
    ])));
    if (!$regNames) {
        return 0.0;
    }
    $scores = [];
    foreach (['first_name', 'last_name'] as $field) {
        $mine = name_key($declared[$field] ?? '');
        $best = 0.0;
        foreach ($regNames as $theirs) {
            $best = max($best, similarity($mine, $theirs));
        }
        $scores[] = $best;
    }
    return round(min($scores), 3);
}

/** Registry dates come as 1982-01-01, 01-01-1982 or 15-Apr-1985. Returns Y-m-d or null. */
function parse_registry_date($value)
{
    $value = trim((string) $value);
    foreach (['Y-m-d', 'd-m-Y', 'd-M-Y', 'd/m/Y', 'Y/m/d'] as $format) {
        $d = DateTime::createFromFormat('!' . $format, $value);
        if ($d && $d->format($format) === $value) {
            return $d->format('Y-m-d');
        }
    }
    return null;
}

/** Age in whole years from a Y-m-d date. */
function age_from_dob($dob)
{
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}
