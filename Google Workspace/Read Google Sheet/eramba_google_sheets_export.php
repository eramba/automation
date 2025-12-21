<?php
/**
 * Eramba Google Workspace -> Google Sheets export (single-run)
 *
 * Reads columns A-D from a Google Spreadsheet and prints JSON to STDOUT.
 * Errors go to STDERR and exit code is non-zero.
 *
 * Required inputs (as env vars in eramba, typically stored as secrets):
 *   {$GOOGLE_SERVICE_ACCOUNT_JSON}  - Full service account key JSON (string)
 *   {$GOOGLE_WORKSPACE_ADMIN_EMAIL} - Workspace admin email to impersonate (Domain-wide delegation)
 *   {$GOOGLE_SHEET_ID}              - Spreadsheet ID
 * Optional:
 *   {$GOOGLE_SHEET_RANGE}           - Defaults to "Sheet1!A:D"
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

function eprintln(string $msg): void {
    fwrite(STDERR, $msg . PHP_EOL);
}

function fail(string $msg, int $code = 1, ?Throwable $ex = null): never {
    eprintln('[ERROR] ' . $msg);
    if ($ex) {
        eprintln('[ERROR] ' . get_class($ex) . ': ' . $ex->getMessage());
    }
    exit($code);
}

function getenv_required(string $name): string {
    $val = getenv($name);
    if ($val === false || trim($val) === '') {
        fail("Missing required environment variable: {$name}");
    }
    // If eramba didn't substitute the secret and we received the placeholder literally, fail loudly.
    if (preg_match('/^\{\$[A-Z0-9_]+\}$/', trim($val))) {
        fail("Environment variable {$name} looks like an unresolved eramba placeholder: {$val}");
    }
    return $val;
}

function getenv_optional(string $name, string $default = ''): string {
    $val = getenv($name);
    if ($val === false || trim($val) === '') {
        return $default;
    }
    if (preg_match('/^\{\$[A-Z0-9_]+\}$/', trim($val))) {
        return $default;
    }
    return $val;
}

$serviceAccountJson = getenv_required('GOOGLE_SERVICE_ACCOUNT_JSON');
$adminEmail         = getenv_required('GOOGLE_WORKSPACE_ADMIN_EMAIL');
$spreadsheetId      = getenv_required('GOOGLE_SHEET_ID');
$range              = getenv_optional('GOOGLE_SHEET_RANGE', 'Sheet1!A:D');

try {
    $decoded = json_decode($serviceAccountJson, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $ex) {
    fail('GOOGLE_SERVICE_ACCOUNT_JSON is not valid JSON.', 2, $ex);
}

if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
    fail('GOOGLE_SERVICE_ACCOUNT_JSON is missing required fields (client_email/private_key).', 2);
}

try {
    $client = new Client();
    $client->setApplicationName('eramba-google-sheets-export');
    $client->setAuthConfig($decoded);

    $client->setScopes([
        'https://www.googleapis.com/auth/spreadsheets.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
    ]);

    // Domain-wide delegation impersonation:
    $client->setSubject($adminEmail);

    $sheets = new Sheets($client);

    $resp = $sheets->spreadsheets_values->get($spreadsheetId, $range);
    $values = $resp->getValues();
    if (!is_array($values)) {
        $values = [];
    }

    $out = [];
    $rowNum = 0;

    foreach ($values as $row) {
        $rowNum++;

        if (!is_array($row)) {
            continue;
        }

        $login      = isset($row[0]) ? trim((string)$row[0]) : '';
        $rolesRaw   = isset($row[1]) ? trim((string)$row[1]) : '';
        $workerType = isset($row[2]) ? trim((string)$row[2]) : '';
        $os         = isset($row[3]) ? trim((string)$row[3]) : '';

        if ($login === '' && $rolesRaw === '' && $workerType === '' && $os === '') {
            continue;
        }

        if ($login === '' || $rolesRaw === '' || $workerType === '') {
            fail("Row {$rowNum} is missing mandatory data. Required: A(login), B(roles), C(type).", 3);
        }

        $roles = array_values(array_filter(array_map(
            static fn($r) => trim($r),
            explode('|', $rolesRaw)
        ), static fn($r) => $r !== ''));

        if (count($roles) === 0) {
            fail("Row {$rowNum} has no valid roles after parsing column B (separator is '|').", 3);
        }

        $out[] = [
            'login' => $login,
            'roles' => $roles,
            'worker_type' => $workerType,
            'os' => ($os === '') ? null : $os,
        ];
    }

    $json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        fail('Failed to encode output as JSON: ' . json_last_error_msg(), 4);
    }

    fwrite(STDOUT, $json . PHP_EOL);
    exit(0);

} catch (Google\Service\Exception $gex) {
    fail('Google API request failed.', 10, $gex);
} catch (Throwable $ex) {
    fail('Unexpected failure.', 11, $ex);
}
