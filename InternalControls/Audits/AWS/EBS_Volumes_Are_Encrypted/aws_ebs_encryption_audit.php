<?php
/**
 * AWS EBS Volume Encryption Audit Script
 *
 * Control objective:
 *   - All EBS volumes in the target AWS account and region (attached AND unattached)
 *     must have encryption enabled.
 *   - Encryption with either the default AWS-managed key or a customer-managed KMS key
 *     is considered compliant.
 *
 * Test success criteria:
 *   - PASS  -> Every non-exempt EBS volume in eu-west-1 is encrypted.
 *   - FAIL  -> At least one non-exempt EBS volume in eu-west-1 is NOT encrypted.
 *
 * Usage:
 *   1) Install dependencies:
 *        composer install
 *   2) Ensure AWS credentials are configured (env vars, shared config, or hard-coded below).
 *   3) Run:
 *        php aws_ebs_encryption_audit.php
 */

require 'vendor/autoload.php';

use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;

// =======================================================
// 1. CONNECTION SETTINGS (url to connect, keys, secrets, etc)
// =======================================================

$awsConfig = [
    'region'  => 'eu-west-1',
    'version' => '2016-11-15',
];

$encryptionExceptions = [
    // 'vol-0123456789abcdef0',
];

try {
    $ec2Client = new Ec2Client($awsConfig);
} catch (AwsException $e) {
    fwrite(STDERR, "Failed to create EC2 client: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// 2. EVIDENCE GATHERING
$allVolumes = [];
$nextToken = null;

try {
    do {
        $params = [];
        if ($nextToken !== null) {
            $params['NextToken'] = $nextToken;
        }

        $result = $ec2Client->describeVolumes($params);

        $volumesPage = $result['Volumes'] ?? [];
        $allVolumes = array_merge($allVolumes, $volumesPage);

        $nextToken = isset($result['NextToken']) ? $result['NextToken'] : null;
    } while ($nextToken !== null);

} catch (AwsException $e) {
    fwrite(STDERR, "Error describing EBS volumes: " . $e->getAwsErrorMessage() . PHP_EOL);
    exit(1);
}

$evidence = [];

foreach ($allVolumes as $vol) {
    $volumeId     = $vol['VolumeId'];
    $encrypted    = isset($vol['Encrypted']) ? (bool)$vol['Encrypted'] : false;
    $kmsKeyId     = $vol['KmsKeyId'] ?? null;
    $volumeType   = $vol['VolumeType'] ?? null;
    $sizeGiB      = $vol['Size'] ?? null;
    $state        = $vol['State'] ?? null;
    $attachments  = $vol['Attachments'] ?? [];
    $tagsRaw      = $vol['Tags'] ?? [];

    $tags = [];
    foreach ($tagsRaw as $tag) {
        if (isset($tag['Key'], $tag['Value'])) {
            $tags[$tag['Key']] = $tag['Value'];
        }
    }

    $evidence[] = [
        'VolumeId'    => $volumeId,
        'Encrypted'   => $encrypted,
        'KmsKeyId'    => $kmsKeyId,
        'VolumeType'  => $volumeType,
        'SizeGiB'     => $sizeGiB,
        'State'       => $state,
        'Attachments' => $attachments,
        'Tags'        => $tags,
    ];
}

// 3. EVIDENCE ANALYSIS
$perVolumeResults = [];
$totalVolumes = count($evidence);
$passCount = 0;
$failCount = 0;
$exemptCount = 0;

foreach ($evidence as $record) {
    $volumeId  = $record['VolumeId'];
    $encrypted = $record['Encrypted'];

    $isExempt = in_array($volumeId, $encryptionExceptions, true);

    if ($isExempt) {
        $status = 'EXEMPT';
        $exemptCount++;
    } elseif ($encrypted) {
        $status = 'PASS';
        $passCount++;
    } else {
        $status = 'FAIL';
        $failCount++;
    }

    $perVolumeResults[] = [
        'VolumeId'    => $volumeId,
        'Encrypted'   => $encrypted,
        'IsExempt'    => $isExempt,
        'Status'      => $status,
        'KmsKeyId'    => $record['KmsKeyId'],
        'VolumeType'  => $record['VolumeType'],
        'SizeGiB'     => $record['SizeGiB'],
        'State'       => $record['State'],
        'Attachments' => $record['Attachments'],
        'Tags'        => $record['Tags'],
    ];
}

$overallStatus = ($failCount > 0) ? 'FAILED' : 'PASSED';

$evidenceDescription = 'Evidence consists of the list of all EBS volumes in region eu-west-1 '
    . 'and their encryption status, KMS key IDs, attachment state, and tags retrieved via the AWS EC2 API (DescribeVolumes).';

$analysisDescription = 'For each EBS volume, the script checks the "Encrypted" flag. Volumes listed in the '
    . '$encryptionExceptions array are treated as EXEMPT. All other volumes must have encryption enabled '
    . '(either with the default AWS-managed key or a customer-managed KMS key). '
    . 'If any non-exempt volume is not encrypted, the overall audit status is FAILED.';

$summary = [
    'overall_status' => $overallStatus,
    'summary' => [
        'total_volumes' => $totalVolumes,
        'pass'          => $passCount,
        'fail'          => $failCount,
        'exempt'        => $exemptCount,
    ],
    'evidence_description' => $evidenceDescription,
    'analysis_description' => $analysisDescription,
    'details' => $perVolumeResults,
];

echo "==============================" . PHP_EOL;
echo " AWS EBS ENCRYPTION AUDIT" . PHP_EOL;
echo "==============================" . PHP_EOL;
echo "Region:               eu-west-1" . PHP_EOL;
echo "Overall audit status: {$overallStatus}" . PHP_EOL;
echo "Total EBS volumes:    {$totalVolumes}" . PHP_EOL;
echo "PASS (encrypted):     {$passCount}" . PHP_EOL;
echo "FAIL (unencrypted):   {$failCount}" . PHP_EOL;
echo "EXEMPT (ignored):     {$exemptCount}" . PHP_EOL;
echo PHP_EOL;
echo "Evidence used: " . $evidenceDescription . PHP_EOL;
echo "Analysis applied: " . $analysisDescription . PHP_EOL;
echo PHP_EOL;
echo "Detailed per-volume results are provided in JSON format below." . PHP_EOL;
echo PHP_EOL;

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;

exit($overallStatus === 'PASSED' ? 0 : 2);
?>