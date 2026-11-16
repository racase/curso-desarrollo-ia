<?php

// Isolated browser review; never boots with local environment files or databases.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';
putenv('APP_DEBUG=true');
$_ENV['APP_LOCALE'] = $_SERVER['APP_LOCALE'] = 'es';
putenv('APP_LOCALE=es');
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
putenv('APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
putenv('DB_CONNECTION=sqlite');
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = '/tmp/infraticket-review.sqlite';
putenv('DB_DATABASE=/tmp/infraticket-review.sqlite');
$_ENV['DB_URL'] = $_SERVER['DB_URL'] = '';
putenv('DB_URL=');
$_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'file';
putenv('SESSION_DRIVER=file');
$_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';
putenv('CACHE_STORE=array');
$_ENV['BCRYPT_ROUNDS'] = $_SERVER['BCRYPT_ROUNDS'] = '4';
putenv('BCRYPT_ROUNDS=4');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useEnvironmentPath(__DIR__)->loadEnvironmentFrom('testing-no-environment');
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'setup') {
    if (! file_exists('/tmp/infraticket-review.sqlite')) {
        touch('/tmp/infraticket-review.sqlite');
    }
    $app->make(Kernel::class)->bootstrap();
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);
    echo 'Isolated browser database ready.'.PHP_EOL;
    exit;
}
if (PHP_SAPI !== 'cli-server') {
    throw new RuntimeException('Run with PHP built-in server, or pass setup.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false;
}
define('LARAVEL_START', microtime(true));
$app->handleRequest(Request::capture());
