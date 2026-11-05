<?php

declare(strict_types=1);

/**
 * Coverage ratchet for the backend.
 *
 * Fails when aggregate coverage drops below the last recorded baseline.
 * The baseline lives in <repo>/.quality/coverage-backend.json and is
 * machine-local (gitignored). A missing baseline is not a failure: it is
 * recorded and the check passes.
 *
 * PCOV has no branch coverage, so only lines and methods are gated.
 */

const METRICS = ['lines', 'methods'];
const TOLERANCE = 0.01;
const SCHEMA_VERSION = 1;

$backendDir = dirname(__DIR__);
$repoRoot = dirname($backendDir);
$reportPath = $backendDir . '/build/clover.xml';
$baselinePath = $repoRoot . '/.quality/coverage-backend.json';

/**
 * @param list<string> $metrics
 * @param array<string, float> $baseline
 * @param array<string, float>|null $current
 */
function printTable(array $metrics, array $baseline, ?array $current = null): void
{
    $hasCurrent = $current !== null;
    $header = sprintf('  %-12s%10s', 'metric', 'baseline');
    if ($hasCurrent) {
        $header .= sprintf('%10s%10s', 'current', 'delta');
    }
    echo rtrim($header) . PHP_EOL;

    foreach ($metrics as $metric) {
        $baselineValue = $baseline[$metric];
        $row = sprintf('  %-12s%9.2f%%', $metric, $baselineValue);

        if ($hasCurrent) {
            $currentValue = $current[$metric];
            $delta = $currentValue - $baselineValue;
            $row .= sprintf('%9.2f%%%9s', $currentValue, sprintf('%+.2fpp', $delta));
        }

        echo $row . PHP_EOL;
    }
}

/**
 * @param array<string, float> $metrics
 */
function writeBaseline(string $path, string $source, array $metrics): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Could not create {$dir}.");
    }

    $payload = [
        'schemaVersion' => SCHEMA_VERSION,
        'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        'source' => $source,
        'metrics' => $metrics,
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException("Could not write {$path}.");
    }
}

/**
 * @return array<string, float>
 */
function readCloverMetrics(string $reportPath): array
{
    if (!is_file($reportPath)) {
        fail('build/clover.xml not found — run "composer coverage" first (the tests may have failed).');
    }

    $xml = @simplexml_load_file($reportPath);
    if ($xml === false || !isset($xml->project->metrics)) {
        fail('Could not read the project metrics from build/clover.xml.');
    }

    $metrics = $xml->project->metrics;
    $statements = (int) $metrics['statements'];
    $coveredStatements = (int) $metrics['coveredstatements'];
    $methods = (int) $metrics['methods'];
    $coveredMethods = (int) $metrics['coveredmethods'];

    if ($statements === 0 || $methods === 0) {
        fail('build/clover.xml contains no statements or methods — check the PHPUnit source configuration.');
    }

    return [
        'lines' => round($coveredStatements / $statements * 100, 4),
        'methods' => round($coveredMethods / $methods * 100, 4),
    ];
}

function fail(string $message): never
{
    fwrite(STDERR, '✗ ' . $message . PHP_EOL);
    exit(1);
}

$current = readCloverMetrics($reportPath);

if (!is_file($baselinePath)) {
    echo '⚠ No coverage baseline at .quality/coverage-backend.json; treating as green and recording it.' . PHP_EOL;
    printTable(METRICS, $current);
    writeBaseline($baselinePath, 'backend/build/clover.xml', $current);
    exit(0);
}

$raw = file_get_contents($baselinePath);
$baseline = $raw === false ? null : json_decode($raw, true);

if (!is_array($baseline) || ($baseline['schemaVersion'] ?? null) !== SCHEMA_VERSION || !is_array($baseline['metrics'] ?? null)) {
    fail('.quality/coverage-backend.json is not a schema version ' . SCHEMA_VERSION
        . ' coverage baseline. Delete it to re-record.');
}

$baselineMetrics = [];
foreach (METRICS as $metric) {
    if (!isset($baseline['metrics'][$metric]) || !is_numeric($baseline['metrics'][$metric])) {
        fail('.quality/coverage-backend.json is missing a numeric "' . $metric
            . '" baseline. Delete it to re-record.');
    }
    $baselineMetrics[$metric] = (float) $baseline['metrics'][$metric];
}

echo 'Coverage ratchet (aggregate)' . PHP_EOL;
printTable(METRICS, $baselineMetrics, $current);

$regressions = [];
foreach (METRICS as $metric) {
    if ($current[$metric] < $baselineMetrics[$metric] - TOLERANCE) {
        $regressions[$metric] = $current[$metric] - $baselineMetrics[$metric];
    }
}

if ($regressions !== []) {
    foreach ($regressions as $metric => $delta) {
        fwrite(
            STDERR,
            sprintf(
                '✗ %s coverage dropped by %.2fpp (baseline %.2f%%, current %.2f%%).%s',
                $metric,
                abs($delta),
                $baselineMetrics[$metric],
                $current[$metric],
                PHP_EOL
            )
        );
    }
    fwrite(STDERR, 'Coverage may not decrease. Add tests rather than lowering the baseline.' . PHP_EOL);
    exit(1);
}

$next = [];
foreach (METRICS as $metric) {
    $next[$metric] = max($baselineMetrics[$metric], $current[$metric]);
}
writeBaseline($baselinePath, 'backend/build/clover.xml', $next);

$raised = array_keys(array_filter(
    $next,
    static fn (float $value, string $metric): bool => $value > $baselineMetrics[$metric],
    ARRAY_FILTER_USE_BOTH
));

if ($raised !== []) {
    echo '✓ Baseline raised for: ' . implode(', ', $raised) . '.' . PHP_EOL;
}

echo '✓ No coverage regression.' . PHP_EOL;
