#!/usr/bin/env php
<?php
/**
 * aws_rds_encryption_backup_check.php
 *
 * Test:
 *   All RDS DB instances (including read replicas) in a single AWS account and region
 *   must:
 *     1) Have storage encryption enabled (StorageEncrypted = true)
 *     2) Have automated backups enabled with BackupRetentionPeriod > 365 days
 *
 * Scope:
 *   - Single AWS account (whatever credentials/profile you run with)
 *   - Single region (default: eu-west-1, override with --region)
 *   - All standard RDS DB instances and replicas returned by describeDBInstances
 *
 * Exit codes:
 *   0 - Test PASSED: all in-scope RDS DB instances compliant
 *   1 - Test FAILED: at least one in-scope instance non-compliant
 *   2 - Script/connection error
 */

require __DIR__ . '/vendor/autoload.php';

use Aws\Rds\RdsClient;
use Aws\Exception\AwsException;

// ==========================================================
// 1. CONNECTION SETTINGS
//    (url/region, keys/profiles, basic config)
// ==========================================================

$config = [
    // Default AWS region if none is provided via CLI
    'default_region' => 'eu-west-1',

    // Backup policy requirement:
    // We want BackupRetentionPeriod > 365, so set minimum to 366.
    'default_min_backup_days' => 366,

    // Optional scoping lists, left empty per current instructions.
    // They are here for future customisation.
    'only_these_db_instance_ids' => [],  // e.g. ['prod-db-1', 'prod-db-2']
    'skip_these_db_instance_ids'  => [], // e.g. ['dev-db-1', 'sandbox-db']
];

// CLI options
$options = getopt('', [
    'region::',
    'profile::',
    'min-backup-days::',
    'help',
]);

if (isset($options['help'])) {
    echo "AWS RDS Encryption & Backup Policy Test\n";
    echo "---------------------------------------\n";
    echo "This script checks that all RDS DB instances in a region are:\n";
    echo "  - Encrypted at rest (StorageEncrypted = true)\n";
    echo "  - Have BackupRetentionPeriod > 365 days (default, configurable)\n\n";
    echo "Options:\n";
    echo "  --region=REGION          AWS region (default: {$config['default_region']})\n";
    echo "  --profile=PROFILE        AWS shared credentials/profile to use\n";
    echo "  --min-backup-days=DAYS   Override minimum backup retention days (default: {$config['default_min_backup_days']})\n";
    echo "  --help                   Show this help message and exit\n";
    exit(0);
}

$region = $options['region'] ?? $config['default_region'];
$profile = $options['profile'] ?? null;
$minBackupDays = isset($options['min-backup-days'])
    ? (int)$options['min-backup-days']
    : (int)$config['default_min_backup_days'];

if ($minBackupDays <= 0) {
    fwrite(STDERR, "ERROR: min-backup-days must be > 0\n");
    exit(2);
}

$clientConfig = [
    'version' => 'latest',
    'region'  => $region,
];

if (!empty($profile)) {
    $clientConfig['profile'] = $profile;
}

try {
    $rdsClient = new RdsClient($clientConfig);
} catch (\Exception $e) {
    fwrite(STDERR, "ERROR: Failed to create RDS client: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

// ==========================================================
// 2. EVIDENCE GATHERING
//    (call AWS APIs to collect raw data)
// ==========================================================

$dbInstances = [];
$marker = null;

try {
    do {
        $params = [];
        if ($marker !== null) {
            $params['Marker'] = $marker;
        }

        $result = $rdsClient->describeDBInstances($params);

        if (isset($result['DBInstances']) && is_array($result['DBInstances'])) {
            $dbInstances = array_merge($dbInstances, $result['DBInstances']);
        }

        $marker = isset($result['Marker']) ? $result['Marker'] : null;
    } while ($marker !== null);
} catch (AwsException $e) {
    fwrite(STDERR, "ERROR: AWS error while describing DB instances: " . $e->getAwsErrorMessage() . PHP_EOL);
    exit(2);
} catch (\Exception $e) {
    fwrite(STDERR, "ERROR: Failed to describe DB instances: " . $e->getMessage() . PHP_EOL);
    exit(2);
}

$totalInstances = count($dbInstances);

// Apply optional include/exclude filters (currently empty, but here for future use)
$onlyIds = $config['only_these_db_instance_ids'];
$skipIds = $config['skip_these_db_instance_ids'];

$filteredInstances = array_filter($dbInstances, function ($db) use ($onlyIds, $skipIds) {
    $id = $db['DBInstanceIdentifier'] ?? null;
    if ($id === null) {
        return false;
    }

    if (!empty($onlyIds) && !in_array($id, $onlyIds, true)) {
        return false;
    }

    if (!empty($skipIds) && in_array($id, $skipIds, true)) {
        return false;
    }

    return true;
});

$inScopeInstances = array_values($filteredInstances);
$inScopeCount = count($inScopeInstances);

if ($inScopeCount === 0) {
    // Evidence documentation still needed even if nothing in scope
    echo "AWS RDS Encryption & Backup Policy Test\n";
    echo "Region: {$region}\n";
    echo "Profile: " . ($profile ?: 'default') . "\n";
    echo "---------------------------------------\n";
    echo "No RDS DB instances in scope.\n";
    echo "Total RDS DB instances discovered in region: {$totalInstances}\n";
    echo "Result: PASS (nothing to evaluate).\n";
    exit(0);
}

// ==========================================================
// 3. EVIDENCE ANALYSIS
//    (evaluate encryption and backup rules)
// ==========================================================

$nonCompliant = [];

foreach ($inScopeInstances as $db) {
    $id   = $db['DBInstanceIdentifier'] ?? 'UNKNOWN_ID';
    $arn  = $db['DBInstanceArn'] ?? 'N/A';
    $engine = $db['Engine'] ?? 'unknown';
    $status = $db['DBInstanceStatus'] ?? 'unknown';

    // Encryption check
    $encrypted = isset($db['StorageEncrypted']) ? (bool)$db['StorageEncrypted'] : false;

    // Backup retention check
    $retention = isset($db['BackupRetentionPeriod']) ? (int)$db['BackupRetentionPeriod'] : 0;
    $backupWindow = $db['PreferredBackupWindow'] ?? 'N/A';

    $issues = [];

    if (!$encrypted) {
        $issues[] = 'ENCRYPTION_DISABLED';
    }

    if ($retention < $minBackupDays) {
        $issues[] = "INSUFFICIENT_BACKUP_RETENTION(current={$retention}, required>={$minBackupDays})";
    }

    if (!empty($issues)) {
        $nonCompliant[] = [
            'id'            => $id,
            'arn'           => $arn,
            'engine'        => $engine,
            'status'        => $status,
            'encrypted'     => $encrypted,
            'retention'     => $retention,
            'backup_window' => $backupWindow,
            'issues'        => $issues,
        ];
    }
}

// ==========================================================
// 4. EVIDENCE DOCUMENTATION
//    (report: evidence used, analysis applied, result)
// ==========================================================

echo "AWS RDS Encryption & Backup Policy Test\n";
echo "Region: {$region}\n";
echo "Profile: " . ($profile ?: 'default') . "\n";
echo "Backup retention requirement: >= {$minBackupDays} days\n";
echo "---------------------------------------\n";

echo "Evidence gathered:\n";
echo "  - Total RDS DB instances discovered in region: {$totalInstances}\n";
echo "  - In-scope RDS DB instances evaluated: {$inScopeCount}\n";
echo "  - Data source: RDS describeDBInstances API\n";
echo "---------------------------------------\n";

if (empty($nonCompliant)) {
    echo "Analysis:\n";
    echo "  - All in-scope RDS DB instances have StorageEncrypted = true\n";
    echo "  - All in-scope RDS DB instances have BackupRetentionPeriod >= {$minBackupDays}\n";
    echo "---------------------------------------\n";
    echo "Result: PASS\n";
    echo "All evaluated RDS DB instances satisfy the encryption and backup policy.\n";
    exit(0);
}

// If we reach here, at least one instance is non-compliant
echo "Analysis:\n";
echo "  - Non-compliant RDS DB instances detected: " . count($nonCompliant) . "\n";
echo "---------------------------------------\n";

foreach ($nonCompliant as $item) {
    echo "DBInstanceIdentifier: {$item['id']}\n";
    echo "  ARN: {$item['arn']}\n";
    echo "  Engine: {$item['engine']}\n";
    echo "  Status: {$item['status']}\n";
    echo "  StorageEncrypted: " . ($item['encrypted'] ? 'true' : 'false') . "\n";
    echo "  BackupRetentionPeriod: {$item['retention']}\n";
    echo "  PreferredBackupWindow: {$item['backup_window']}\n";
    echo "  Issues:\n";
    foreach ($item['issues'] as $issue) {
        echo "    - {$issue}\n";
    }
    echo "---------------------------------------\n";
}

echo "Result: FAIL\n";
echo "At least one RDS DB instance does not meet the encryption and/or backup requirements.\n";

exit(1);
