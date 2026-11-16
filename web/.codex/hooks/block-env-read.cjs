'use strict';

// Inspect tool arguments only. Never open the requested files or execute commands.
const protectedName = /(?<![a-z0-9_.-])\.env(?:\.[a-z0-9_.-]+)?(?![a-z0-9_.-])/gi;
const pathFields = new Set(['path', 'paths', 'file_path', 'file_paths', 'filepath', 'filename', 'file', 'files', 'uri', 'url', 'target_file']);

function mentionsEnvironmentFile(value) {
    return [...value.matchAll(protectedName)].some((match) => match[0].toLowerCase() !== '.env.example');
}

function inspect(value, executableTool, field = '') {
    if (typeof value === 'string') {
        const key = field.toLowerCase();
        const isCommand = executableTool && ['command', 'cmd', 'script', 'code'].includes(key);
        return (pathFields.has(key) || isCommand) && mentionsEnvironmentFile(value);
    }
    if (Array.isArray(value)) {
        return value.some((item) => inspect(item, executableTool, field));
    }
    if (value && typeof value === 'object') {
        return Object.entries(value).some(([key, item]) => inspect(item, executableTool, key));
    }
    return false;
}

let input = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => { input += chunk; });
process.stdin.on('error', () => {
    console.error('No se pudo comprobar la llamada. Herramienta bloqueada por seguridad.');
    process.exitCode = 2;
});
process.stdin.on('end', () => {
    try {
        const payload = JSON.parse(input.replace(/^\uFEFF/, ''));
        if (!payload || payload.hook_event_name !== 'PreToolUse' || typeof payload.tool_name !== 'string' || !payload.tool_input || typeof payload.tool_input !== 'object') {
            throw new Error('Invalid hook payload');
        }
        const executableTool = /bash|exec|repl|script|javascript|node|\bjs\b/i.test(payload.tool_name);
        if (inspect(payload.tool_input, executableTool)) {
            process.stdout.write(JSON.stringify({
                hookSpecificOutput: {
                    hookEventName: 'PreToolUse',
                    permissionDecision: 'deny',
                    permissionDecisionReason: 'Acceso a archivos de entorno bloqueado por la política del proyecto. Usa .env.example como referencia.',
                },
            }) + '\n');
        }
        // Empty output preserves normal permissions for unrelated calls.
    } catch {
        console.error('Entrada del hook no válida. Herramienta bloqueada por seguridad.');
        process.exitCode = 2;
    }
});
