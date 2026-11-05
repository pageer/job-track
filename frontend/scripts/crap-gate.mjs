#!/usr/bin/env node
/**
 * CRAP gate for the frontend.
 *
 * Runs react-crap over the LCOV report produced by `npm run test:coverage`.
 * The threshold lives in frontend/.react-crap.json. It is blocking: any
 * function above the threshold fails the gate.
 *
 * The threshold started at the highest score in the codebase (17292) and is
 * meant to be lowered towards 30 as complex code is refactored. A warning is
 * printed on every run until it reaches 30.
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const TARGET_THRESHOLD = 30;

const scriptDir = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(scriptDir, '..');
const repoRoot = resolve(frontendDir, '..');
const lcovPath = resolve(frontendDir, 'coverage/lcov.info');
const configPath = resolve(frontendDir, '.react-crap.json');
const binPath = resolve(
  frontendDir,
  'node_modules/@omermohideen/react-crap/dist/bin/react-crap.js',
);

function fail(message) {
  console.error(`✗ ${message}`);
  process.exit(1);
}

function display(path) {
  return relative(repoRoot, path).replaceAll('\\', '/');
}

if (!existsSync(lcovPath)) {
  fail(
    `${display(lcovPath)} not found — run "npm run test:coverage" first (the tests may have failed).`,
  );
}

if (!existsSync(binPath)) {
  fail('react-crap is not installed — run "npm install" in frontend/.');
}

let config = {};
if (existsSync(configPath)) {
  try {
    config = JSON.parse(readFileSync(configPath, 'utf8'));
  } catch (error) {
    fail(`Could not parse ${display(configPath)}: ${error.message}`);
  }
}

const threshold = Number(config.threshold ?? TARGET_THRESHOLD);
if (!Number.isFinite(threshold) || threshold <= 0) {
  fail(
    `${display(configPath)} has an invalid "threshold" value: ${JSON.stringify(config.threshold)}`,
  );
}

const result = spawnSync(
  process.execPath,
  [
    binPath,
    '--lcov',
    lcovPath,
    '--path',
    'src',
    '--threshold',
    String(threshold),
    '--fail-above',
    '--format',
    'json',
  ],
  { cwd: frontendDir, encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 },
);

let report;
try {
  report = JSON.parse(result.stdout);
} catch {
  if (result.stderr) console.error(result.stderr);
  fail('react-crap did not produce a JSON report.');
}

const entries = Array.isArray(report.entries) ? report.entries : [];
const maxCrap = entries.reduce((max, entry) => Math.max(max, entry.crap), 0);
const overThreshold = entries.filter((entry) => entry.crap > threshold);

console.log('CRAP gate (react-crap)');
console.log(`  functions analyzed: ${entries.length}`);
console.log(`  max CRAP:           ${maxCrap.toFixed(1)}`);
console.log(
  `  threshold:          ${threshold} (${overThreshold.length} over)`,
);

if (threshold > TARGET_THRESHOLD) {
  console.log(
    `⚠ CRAP threshold is ${threshold}; the target is ${TARGET_THRESHOLD}. ` +
      'Lower it in frontend/.react-crap.json as complex code is refactored.',
  );
}

if (overThreshold.length > 0) {
  console.error('');
  console.error(
    `✗ ${overThreshold.length} function(s) exceed the CRAP threshold of ${threshold}:`,
  );
  const worst = [...overThreshold].sort((a, b) => b.crap - a.crap).slice(0, 10);
  for (const entry of worst) {
    const file = display(entry.file);
    console.error(
      `  ${entry.crap.toFixed(1).padStart(10)}  ${entry.function} (${file}:${entry.line}, ` +
        `cyclomatic ${entry.cyclomatic}, coverage ${Number(entry.coverage ?? 0).toFixed(1)}%)`,
    );
  }
  if (overThreshold.length > worst.length) {
    console.error(`  … and ${overThreshold.length - worst.length} more`);
  }
  process.exit(1);
}

if (result.status !== 0) {
  // react-crap failed for a reason other than the threshold (e.g. a parse error).
  if (result.stderr) console.error(result.stderr);
  fail(`react-crap exited with code ${result.status}.`);
}

console.log('✓ No function exceeds the CRAP threshold.');
