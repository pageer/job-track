#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Unified control utility for Job Track.
 *
 * Usage:
 *   php jobtrack.php deploy [--no-migrate]
 *   php jobtrack.php push-assets
 *   php jobtrack.php local-build
 *
 * Connection settings for deploy/push-assets are read from .env in the
 * project root (REMOTE_USER, REMOTE_HOST, REMOTE_PATH, REMOTE_DEPLOY_DIR,
 * REMOTE_PORT).
 */

const APP_NAME = 'jobtrack';

final class Color
{
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const CYAN = "\033[36m";
}

try {
    exit(main($argv));
} catch (RuntimeException $e) {
    error($e->getMessage());
    exit(1);
}

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
    array_shift($argv);
    $command = $argv[0] ?? null;
    $args = array_slice($argv, 1);

    if ($command === null) {
        printUsage();

        return 1;
    }

    if (in_array($command, ['-h', '--help', 'help'], true)) {
        printUsage();

        return 0;
    }

    return match ($command) {
        'deploy' => cmdDeploy($args),
        'push-assets' => cmdPushAssets($args),
        'local-build' => cmdLocalBuild($args),
        default => throw new RuntimeException("Unknown command: {$command}\n\n" . usageText()),
    };
}

function rootDir(): string
{
    return __DIR__;
}

function cmdDeploy(array $args): int
{
    $options = parseOptions($args, ['no-migrate']);

    if (isset($options['help'])) {
        println('Usage: php jobtrack.php deploy [--no-migrate]');
        println('');
        println('Runs deploy.sh --no-build on the remote server, then builds and');
        println('uploads the frontend assets.');
        println('');
        println('  --no-migrate   Skip database migrations on the server');
        println('  -h, --help     Show this help');

        return 0;
    }

    $root = rootDir();
    $env = loadEnvFile($root . '/.env');

    requireCommands(['git', 'ssh'], $root);
    $config = requireEnv($env, ['REMOTE_USER', 'REMOTE_HOST', 'REMOTE_DEPLOY_DIR'], '.env');
    $port = remotePort($env);

    println('');
    println(style(APP_NAME . ' deploy', Color::BOLD));
    println('');
    println('Preflight checks...');
    gitPreflight($root);

    $remoteScript = rtrim($config['REMOTE_DEPLOY_DIR'], '/\\') . '/deploy.sh';
    $remoteCommand = 'bash ' . shellQuote($remoteScript) . ' --no-build';
    if (isset($options['no-migrate'])) {
        $remoteCommand .= ' --no-migrate';
    }

    step('Running deploy.sh --no-build on the server');
    runOrFail(
        ['ssh', '-p', $port, $config['REMOTE_USER'] . '@' . $config['REMOTE_HOST'], $remoteCommand],
        $root,
        'Remote deploy failed'
    );
    ok('Server deploy complete.');

    println('');
    step('Building and uploading frontend assets');
    pushAssets($root, $env);

    println('');
    ok('Deploy complete.');

    return 0;
}

function cmdPushAssets(array $args): int
{
    $options = parseOptions($args, []);

    if (isset($options['help'])) {
        println('Usage: php jobtrack.php push-assets');
        println('');
        println('Builds the frontend locally and uploads backend/public/build/');
        println('to the server via SCP, then fixes remote permissions.');

        return 0;
    }

    $root = rootDir();
    $env = loadEnvFile($root . '/.env');

    println('');
    println(style(APP_NAME . ' push-assets', Color::BOLD));
    println('');
    pushAssets($root, $env);

    println('');
    ok('Done.');

    return 0;
}

function cmdLocalBuild(array $args): int
{
    $options = parseOptions($args, []);

    if (isset($options['help'])) {
        println('Usage: php jobtrack.php local-build');
        println('');
        println('Runs "npm run build" in frontend/ and applies database migrations.');

        return 0;
    }

    $root = rootDir();

    requireCommands(['npm', 'php'], $root);

    println('');
    println(style(APP_NAME . ' local-build', Color::BOLD));
    println('');

    step('Building frontend');
    runOrFail(['npm', 'run', 'build'], $root . '/frontend', 'Frontend build failed');
    ok('Frontend built into backend/public/build/.');

    step('Applying database migrations');
    runOrFail(
        ['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'],
        $root . '/backend',
        'Database migrations failed'
    );
    ok('Database migrations applied.');

    println('');
    ok('Local build complete.');

    return 0;
}

/**
 * Build the frontend and upload it to the server.
 *
 * @param array<string, string> $env
 */
function pushAssets(string $root, array $env): void
{
    requireCommands(['npm', 'scp', 'ssh'], $root);
    $config = requireEnv($env, ['REMOTE_USER', 'REMOTE_HOST', 'REMOTE_PATH'], '.env');
    $port = remotePort($env);

    $frontend = $root . '/frontend';
    $buildDir = $root . '/backend/public/build';
    $remote = $config['REMOTE_USER'] . '@' . $config['REMOTE_HOST'];
    $remotePath = rtrim($config['REMOTE_PATH'], '/');
    $remoteBuildDir = $remotePath . '/build';

    step('Installing frontend dependencies');
    runOrFail(['npm', 'install', '--no-audit', '--no-fund'], $frontend, 'npm install failed');
    ok('Frontend dependencies installed.');

    step('Building frontend');
    runOrFail(['npm', 'run', 'build'], $frontend, 'Frontend build failed');
    ok('Frontend built into backend/public/build/.');

    step("Uploading assets to {$remote}:{$remotePath}");
    runOrFail(['scp', '-P', $port, '-r', $buildDir, $remote . ':' . $remotePath], $root, 'SCP upload failed');
    ok('Assets uploaded.');

    step('Fixing remote directory permissions');
    runOrFail(
        ['ssh', '-p', $port, $remote, 'chmod -R +rX ' . shellQuote($remoteBuildDir)],
        $root,
        'Remote chmod failed'
    );
    ok('Remote permissions fixed.');
}

/**
 * @param list<string> $args
 * @param list<string> $allowedFlags
 * @return array<string, true>
 */
function parseOptions(array $args, array $allowedFlags): array
{
    $options = [];

    foreach ($args as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
            continue;
        }

        if (!str_starts_with($arg, '--')) {
            throw new RuntimeException("Unexpected argument: {$arg}");
        }

        $name = substr($arg, 2);
        if (!in_array($name, $allowedFlags, true)) {
            throw new RuntimeException("Unknown option: {$arg}");
        }

        $options[$name] = true;
    }

    return $options;
}

/**
 * @return array<string, string>
 */
function loadEnvFile(string $file): array
{
    if (!is_file($file)) {
        throw new RuntimeException(
            "Missing {$file}. Copy .env.example to .env and fill in your connection settings."
        );
    }

    $vars = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}.");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $equals = strpos($line, '=');
        if ($equals === false || $equals === 0) {
            continue;
        }

        $key = trim(substr($line, 0, $equals));
        $value = trim(substr($line, $equals + 1));

        if (str_starts_with($key, 'export ')) {
            $key = trim(substr($key, 7));
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $vars[$key] = $value;
    }

    return $vars;
}

/**
 * @param array<string, string> $env
 * @param list<string> $keys
 * @return array<string, string>
 */
function requireEnv(array $env, array $keys, string $source): array
{
    $missing = [];
    $values = [];

    foreach ($keys as $key) {
        $value = $env[$key] ?? '';
        if ($value === '') {
            $missing[] = $key;
            continue;
        }
        $values[$key] = $value;
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Missing required value(s) in ' . $source . ': ' . implode(', ', $missing)
        );
    }

    return $values;
}

/**
 * @param array<string, string> $env
 */
function remotePort(array $env): string
{
    $port = $env['REMOTE_PORT'] ?? '';

    return $port === '' ? '22' : $port;
}

function gitPreflight(string $root): void
{
    if (!commandExists('git', $root)) {
        warn('git not found in PATH; skipping preflight checks.');

        return;
    }

    $status = run(['git', 'status', '--porcelain'], $root, true);
    if ($status['exit'] === 0) {
        $changed = array_filter(explode("\n", trim($status['stdout'])));
        if ($changed !== []) {
            warn(
                count($changed) . ' changed file(s) in the working tree. '
                . 'The deploy uses only what is pushed to origin.'
            );
        }
    }

    $branchResult = run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $root, true);
    $branch = trim($branchResult['stdout']);
    if ($branchResult['exit'] !== 0 || $branch === '' || $branch === 'HEAD') {
        return;
    }

    $ahead = run(['git', 'rev-list', '--count', '@{upstream}..HEAD'], $root, true);
    if ($ahead['exit'] !== 0) {
        $ahead = run(['git', 'rev-list', '--count', 'origin/' . $branch . '..HEAD'], $root, true);
    }

    if ($ahead['exit'] === 0) {
        $count = (int) trim($ahead['stdout']);
        if ($count > 0) {
            warn(
                "{$count} unpushed commit(s) on '{$branch}'. "
                . "Run 'git push origin {$branch}' or they won't be deployed."
            );
        }
    }
}

/**
 * @param list<string> $commands
 */
function requireCommands(array $commands, string $cwd): void
{
    $missing = [];

    foreach ($commands as $command) {
        if (!commandExists($command, $cwd)) {
            $missing[] = $command;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Required command(s) not found in PATH: ' . implode(', ', $missing)
        );
    }
}

function commandExists(string $command, string $cwd): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return run(['where', $command], $cwd, true)['exit'] === 0;
    }

    return run(['sh', '-c', 'command -v ' . escapeshellarg($command)], $cwd, true)['exit'] === 0;
}

/**
 * Run a command, inheriting stdio unless capturing output.
 *
 * @param list<string> $command
 * @return array{exit: int, stdout: string, stderr: string}
 */
function run(array $command, string $cwd, bool $capture = false): array
{
    if (!$capture) {
        println(style('$ ' . implode(' ', $command), Color::DIM));
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $line = 'cmd /c ' . $command[0] . ' '
            . implode(' ', array_map('escapeshellarg', array_slice($command, 1)));
        $descriptorSpec = $capture
            ? [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']]
            : [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $process = proc_open($line, $descriptorSpec, $pipes, $cwd);
    } else {
        $descriptorSpec = $capture
            ? [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']]
            : [0 => STDIN, 1 => STDOUT, 2 => STDERR];
        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    }

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start command: ' . implode(' ', $command));
    }

    $stdout = '';
    $stderr = '';

    if ($capture) {
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    }

    $exit = proc_close($process);

    return [
        'exit' => $exit,
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

/**
 * @param list<string> $command
 */
function runOrFail(array $command, string $cwd, string $message): void
{
    $result = run($command, $cwd);

    if ($result['exit'] !== 0) {
        throw new RuntimeException("{$message} (exit code {$result['exit']}).");
    }
}

function shellQuote(string $value): string
{
    return "'" . str_replace("'", "'\\''", $value) . "'";
}

function step(string $message): void
{
    println(style('→ ' . $message . '...', Color::CYAN));
}

function ok(string $message): void
{
    println(style('✓ ' . $message, Color::GREEN));
}

function warn(string $message): void
{
    println(style('⚠ ' . $message, Color::YELLOW));
}

function error(string $message): void
{
    fwrite(STDERR, style('✗ ' . $message, Color::RED) . PHP_EOL);
}

function println(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function printUsage(): void
{
    println(usageText());
}

function usageText(): string
{
    return <<<'TEXT'
Usage: php jobtrack.php <command> [options]

Commands:
  deploy        Run deploy.sh --no-build on the server, then upload frontend assets
  push-assets   Build the frontend locally and upload it via SCP
  local-build   Run "npm run build" and apply database migrations locally

Options:
  -h, --help    Show help for a command

Connection settings are read from .env in the project root
(REMOTE_USER, REMOTE_HOST, REMOTE_PATH, REMOTE_DEPLOY_DIR, REMOTE_PORT).
TEXT;
}

function style(string $text, string $color): string
{
    if (!supportsColor()) {
        return $text;
    }

    return $color . $text . "\033[0m";
}

function supportsColor(): bool
{
    static $enabled = null;

    if ($enabled !== null) {
        return $enabled;
    }

    if (getenv('NO_COLOR') !== false) {
        return $enabled = false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        if (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)) {
            return $enabled = true;
        }

        return $enabled = getenv('WT_SESSION') !== false
            || getenv('TERM_PROGRAM') !== false
            || getenv('ANSICON') !== false;
    }

    return $enabled = function_exists('stream_isatty') ? @stream_isatty(STDOUT) : true;
}
