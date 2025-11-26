<?php
/**
 * AWS IAM MFA Audit Script
 *
 * - Connects to AWS IAM using AWS SDK for PHP
 * - Checks if ALL IAM users (except explicit exceptions) have at least one MFA device
 * - If any non-exempt user does NOT have MFA, the audit FAILS
 *
 * Usage:
 *   composer install
 *   php aws_iam_mfa_audit.php
 */

require 'vendor/autoload.php';

use Aws\Iam\IamClient;
use Aws\Exception\AwsException;

// =======================================================
// 1. CONNECTION SETTINGS (url to connect, keys, secrets, etc)
// =======================================================

/**
 * AWS SDK configuration.
 *
 * Credentials resolution order is handled by the AWS SDK:
 * - Environment variables (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION, etc)
 * - ~/.aws/credentials and ~/.aws/config
 * - EC2/ECS instance roles, etc.
 *
 * You can hard-code credentials here if you really want to, but it's not recommended.
 */
$awsConfig = [
    'region'  => 'eu-west-1',           // Change region if needed
    'version' => '2010-05-08',          // IAM API version
    // 'credentials' => [
    //     'key'    => 'YOUR_AWS_ACCESS_KEY_ID',
    //     'secret' => 'YOUR_AWS_SECRET_ACCESS_KEY',
    // ],
];

/**
 * IAM usernames that are explicitly exempt from the MFA requirement
 * (for example service accounts, automation accounts, etc).
 */
$mfaExceptions = [
    // 'service-account-1',
    // 'automation-user',
];

try {
    $iamClient = new IamClient($awsConfig);
} catch (AwsException $e) {
    fwrite(STDERR, "Failed to create IAM client: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// =======================================================
// 2. EVIDENCE GATHERING
//    - Retrieve list of all IAM users
//    - For each user, retrieve their associated MFA devices
// =======================================================

$allUsers = [];
$marker = null;

try {
    do {
        $params = [];
        if ($marker !== null) {
            $params['Marker'] = $marker;
        }

        $result = $iamClient->listUsers($params);
        $usersPage = $result['Users'] ?? [];
        $allUsers = array_merge($allUsers, $usersPage);

        $marker = (!empty($result['IsTruncated'])) ? ($result['Marker'] ?? null) : null;

    } while ($marker !== null);

} catch (AwsException $e) {
    fwrite(STDERR, "Error listing IAM users: " . $e->getAwsErrorMessage() . PHP_EOL);
    exit(1);
}

// Evidence structure: one entry per IAM user with MFA device list and any errors
$evidence = [];

foreach ($allUsers as $user) {
    $userName = $user['UserName'];

    try {
        $mfaResult = $iamClient->listMFADevices([
            'UserName' => $userName,
        ]);

        $mfaDevices = $mfaResult['MFADevices'] ?? [];

        $evidence[] = [
            'UserName'   => $userName,
            'Arn'        => $user['Arn'],
            'CreateDate' => $user['CreateDate']->format('c'),
            'MfaDevices' => array_map(function ($dev) {
                return [
                    'SerialNumber' => $dev['SerialNumber'],
                    'EnableDate'   => $dev['EnableDate']->format('c'),
                ];
            }, $mfaDevices),
        ];

    } catch (AwsException $e) {
        // Capture the error as evidence as well
        $evidence[] = [
            'UserName'   => $userName,
            'Arn'        => $user['Arn'],
            'CreateDate' => $user['CreateDate']->format('c'),
            'MfaDevices' => [],
            'Error'      => $e->getAwsErrorMessage(),
        ];
    }
}

// =======================================================
// 3. EVIDENCE ANALYSIS
//    - For each user, determine status:
//      PASS  = non-exempt user with at least one MFA device
//      FAIL  = non-exempt user with no MFA device or an error
//      EXEMPT = user in the exception list
//    - Overall result: if ANY non-exempt user fails, the entire audit FAILS
// =======================================================

$perUserResults = [];
$totalUsers = count($evidence);
$passCount = 0;
$failCount = 0;
$exemptCount = 0;

foreach ($evidence as $record) {
    $userName = $record['UserName'];
    $hasMfa   = !empty($record['MfaDevices']);
    $isExempt = in_array($userName, $mfaExceptions, true);
    $hasError = !empty($record['Error'] ?? null);

    if ($isExempt) {
        $status = 'EXEMPT';
        $exemptCount++;
    } elseif ($hasError) {
        // Treat any error as a failure for audit purposes
        $status = 'FAIL (ERROR)';
        $failCount++;
    } elseif ($hasMfa) {
        $status = 'PASS';
        $passCount++;
    } else {
        $status = 'FAIL';
        $failCount++;
    }

    $perUserResults[] = [
        'UserName'    => $userName,
        'Arn'         => $record['Arn'],
        'CreateDate'  => $record['CreateDate'],
        'HasMfa'      => $hasMfa,
        'IsExempt'    => $isExempt,
        'Status'      => $status,
        'MfaDevices'  => $record['MfaDevices'],
        'Error'       => $record['Error'] ?? null,
    ];
}

// Overall audit result: ALL non-exempt users must have MFA, otherwise FAIL
$overallStatus = ($failCount > 0) ? 'FAILED' : 'PASSED';

// =======================================================
// 4. EVIDENCE DOCUMENTATION
//    - Brief report that includes:
//        * Evidence used
//        * Analysis applied
//        * Result of the test (PASS or FAILED)
// =======================================================

$summary = [
    'overall_status' => $overallStatus,
    'summary' => [
        'total_users'  => $totalUsers,
        'pass'         => $passCount,
        'fail'         => $failCount,
        'exempt'       => $exemptCount,
    ],
    'evidence_description' => 'Evidence consists of the list of IAM users and their associated MFA devices retrieved via AWS IAM API (listUsers, listMFADevices).',
    'analysis_description' => 'For each IAM user, the script checks if at least one MFA device is configured. Non-exempt users without MFA or with API errors are marked as FAIL. If any non-exempt user fails, the overall audit status is FAILED.',
    'details' => $perUserResults,
];

// Human-readable brief report
echo "==============================" . PHP_EOL;
echo " AWS IAM MFA AUDIT REPORT" . PHP_EOL;
echo "==============================" . PHP_EOL;
echo "Overall audit status: {$overallStatus}" . PHP_EOL;
echo "Total IAM users:      {$totalUsers}" . PHP_EOL;
echo "PASS (MFA present):   {$passCount}" . PHP_EOL;
echo "FAIL (no MFA/error):  {$failCount}" . PHP_EOL;
echo "EXEMPT (ignored):     {$exemptCount}" . PHP_EOL;
echo PHP_EOL;
echo "Evidence used: " . $summary['evidence_description'] . PHP_EOL;
echo "Analysis applied: " . $summary['analysis_description'] . PHP_EOL;
echo PHP_EOL;
echo "Detailed per-user results are provided in JSON format below." . PHP_EOL;
echo PHP_EOL;

// JSON output for tooling / storage as audit evidence
echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;

// Exit code: 0 if PASSED, 2 if FAILED
exit($overallStatus === 'PASSED' ? 0 : 2);
?>