#!/usr/bin/env node
/**
 * Coverage ratchet for the frontend.
 *
 * Fails when aggregate coverage drops below the last recorded baseline.
 * The baseline lives in <repo>/.quality/coverage-frontend.json and is
 * machine-local (gitignored). A missing baseline is not a failure: it is
 * recorded and the check passes.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const METRICS = ['lines', 'statements', 'functions', 'branches'];
const TOLERANCE = 0.01;
const SCHEMA_VERSION = 1;

const scriptDir = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(scriptDir, '..');
const repoRoot = resolve(frontendDir, '..');
const summaryPath = resolve(frontendDir, 'coverage/coverage-summary.json');
const baselinePath = resolve(repoRoot, '.quality/coverage-frontend.json');

function fail(message) {
  console.error(`✗ ${message}`);
  process.exit(1);
}

function display(path) {
  return relative(repoRoot, path).replaceAll('\\', '/');
}

function printTable(baselineMetrics, currentMetrics = null) {
  const hasCurrent = currentMetrics !== null;
  console.log(
    `  ${'metric'.padEnd(12)}${'baseline'.padStart(10)}${hasCurrent ? 'current'.padStart(10) : ''}${
      hasCurrent ? 'delta'.padStart(10) : ''
    }`,
  );
  for (const metric of METRICS) {
    const baselineValue = baselineMetrics[metric];
    let row = `  ${metric.padEnd(12)}${`${baselineValue.toFixed(2)}%`.padStart(10)}`;
    if (hasCurrent) {
      const currentValue = currentMetrics[metric];
      const delta = currentValue - baselineValue;
      const sign = delta > 0 ? '+' : '';
      row += `${`${currentValue.toFixed(2)}%`.padStart(10)}${`${sign}${delta.toFixed(2)}pp`.padStart(10)}`;
    }
    console.log(row);
  }
}

function writeBaseline(metrics) {
  mkdirSync(dirname(baselinePath), { recursive: true });
  const payload = {
    schemaVersion: SCHEMA_VERSION,
    updatedAt: new Date().toISOString(),
    source: display(summaryPath),
    metrics,
  };
  writeFileSync(baselinePath, `${JSON.stringify(payload, null, 2)}\n`);
}

if (!existsSync(summaryPath)) {
  fail(
    `${display(summaryPath)} not found — run "npm run test:coverage" first (the tests may have failed).`,
  );
}

let summary;
try {
  summary = JSON.parse(readFileSync(summaryPath, 'utf8')).total;
} catch (error) {
  fail(`Could not read ${display(summaryPath)}: ${error.message}`);
}

const current = {};
for (const metric of METRICS) {
  const value = summary?.[metric]?.pct;
  if (typeof value !== 'number') {
    fail(`Coverage summary is missing a numeric "${metric}" percentage.`);
  }
  current[metric] = value;
}

if (!existsSync(baselinePath)) {
  console.log(
    `⚠ No coverage baseline at ${display(baselinePath)}; treating as green and recording it.`,
  );
  printTable(current);
  writeBaseline(current);
  process.exit(0);
}

let baseline;
try {
  baseline = JSON.parse(readFileSync(baselinePath, 'utf8'));
} catch (error) {
  fail(
    `Could not parse ${display(baselinePath)}: ${error.message}. Delete it to re-record the baseline.`,
  );
}

if (
  baseline?.schemaVersion !== SCHEMA_VERSION ||
  typeof baseline.metrics !== 'object'
) {
  fail(
    `${display(baselinePath)} is not a schema version ${SCHEMA_VERSION} coverage baseline. Delete it to re-record.`,
  );
}

for (const metric of METRICS) {
  if (typeof baseline.metrics[metric] !== 'number') {
    fail(
      `${display(baselinePath)} is missing a numeric "${metric}" baseline. Delete it to re-record.`,
    );
  }
}

console.log('Coverage ratchet (aggregate)');
printTable(baseline.metrics, current);

const regressions = METRICS.filter(
  (metric) => current[metric] < baseline.metrics[metric] - TOLERANCE,
);

if (regressions.length > 0) {
  for (const metric of regressions) {
    const delta = current[metric] - baseline.metrics[metric];
    console.error(
      `✗ ${metric} coverage dropped by ${Math.abs(delta).toFixed(2)}pp ` +
        `(baseline ${baseline.metrics[metric].toFixed(2)}%, current ${current[metric].toFixed(2)}%).`,
    );
  }
  console.error(
    'Coverage may not decrease. Add tests rather than lowering the baseline.',
  );
  process.exit(1);
}

const next = {};
for (const metric of METRICS) {
  next[metric] = Math.max(baseline.metrics[metric], current[metric]);
}
writeBaseline(next);

const raised = METRICS.filter(
  (metric) => next[metric] > baseline.metrics[metric],
);
if (raised.length > 0) {
  console.log(`✓ Baseline raised for: ${raised.join(', ')}.`);
}
console.log('✓ No coverage regression.');
