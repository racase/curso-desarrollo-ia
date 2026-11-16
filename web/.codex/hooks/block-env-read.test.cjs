'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');
const hookPath = path.join(__dirname, 'block-env-read.cjs');

function invoke(toolName, toolInput) {
    return spawnSync(process.execPath, [hookPath], {
        input: JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: toolName, tool_input: toolInput }),
        encoding: 'utf8',
    });
}

const denied = [
    ['Read', { file_path: '.env' }],
    ['Read', { file_path: 'C:\\projects\\web\\.ENV' }],
    ['mcp__filesystem__read_file', { path: '/project/web/.env' }],
    ['mcp__filesystem__read_multiple_files', { paths: ['README.md', '/project/.env.local'] }],
    ['Bash', { command: 'cat .env' }],
    ['Bash', { command: 'Get-Content -LiteralPath "C:\\a folder\\.env"' }],
    ['exec_command', { cmd: 'rg password .env.production' }],
    ['Bash', { command: 'python -c "open(\'.env\').read()"' }],
    ['Bash', { command: 'cat .env*' }],
    ['mcp__node_repl__js', { code: 'fs.readFileSync(".env", "utf8")' }],
    ['Bash', { command: ['cat', '.env'] }],
    ['Read', { filePath: './.env.testing' }],
];
for (const [index, [toolName, toolInput]] of denied.entries()) {
    test(`blocks protected input ${index + 1}`, () => {
        const result = invoke(toolName, toolInput);
        assert.equal(result.status, 0, result.stderr);
        assert.equal(JSON.parse(result.stdout).hookSpecificOutput.permissionDecision, 'deny');
    });
}

const allowed = [
    ['Read', { file_path: '.env.example' }],
    ['Read', { file_path: 'C:\\a folder\\.env.example' }],
    ['Read', { path: 'README.md' }],
    ['Read', { path: 'config/environment.php' }],
    ['Bash', { command: 'Get-Content .env.example' }],
    ['Bash', { command: 'npm test' }],
    ['mcp__node_repl__js', { code: 'console.log(process.env.NODE_ENV)' }],
    ['apply_patch', { patch: 'Documentation mentioning .env', content: '.env' }],
    ['spawn_agent', { prompt: 'Do not read .env; use .env.example.' }],
];
for (const [index, [toolName, toolInput]] of allowed.entries()) {
    test(`preserves normal permissions ${index + 1}`, () => {
        const result = invoke(toolName, toolInput);
        assert.equal(result.status, 0, result.stderr);
        assert.equal(result.stdout, '');
    });
}

test('rejects malformed input without printing the payload', () => {
    for (const input of ['invalid-sensitive-input', '{}', 'null']) {
        const result = spawnSync(process.execPath, [hookPath], { input, encoding: 'utf8' });
        assert.equal(result.status, 2);
        assert.equal(result.stdout, '');
        assert.doesNotMatch(result.stderr, /invalid-sensitive-input/);
    }
});

test('accepts a UTF-8 BOM from Windows pipelines', () => {
    const input = '\uFEFF' + JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: 'Read', tool_input: { file_path: '.env' } });
    const result = spawnSync(process.execPath, [hookPath], { input, encoding: 'utf8' });
    assert.equal(JSON.parse(result.stdout).hookSpecificOutput.permissionDecision, 'deny');
});

test('does not treat an apply_patch body as a shell read', () => {
    const result = invoke('apply_patch', { command: '*** Begin Patch\nDocumentation: use .env.example instead of .env\n*** End Patch' });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.stdout, '');
});

test('configured launcher works from a nested project directory', () => {
    const fs = require('node:fs');
    const config = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'hooks.json'), 'utf8'));
    const command = config.hooks.PreToolUse[0].hooks[0].command;
    const isWindows = process.platform === 'win32';
    const executable = isWindows ? 'powershell.exe' : '/bin/sh';
    const args = isWindows ? ['-NoProfile', '-NonInteractive', '-Command', command] : ['-c', command];
    const result = spawnSync(executable, args, {
        cwd: path.resolve(__dirname, '..', '..', 'app'),
        input: JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: 'Read', tool_input: { file_path: '.env' } }),
        encoding: 'utf8',
    });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(JSON.parse(result.stdout).hookSpecificOutput.permissionDecision, 'deny');
});
