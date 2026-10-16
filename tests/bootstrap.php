<?php
define('BASEPATH', dirname(__DIR__));
require BASEPATH . '/includes/config.php';
require BASEPATH . '/vendor/autoload.php';

// Uploads in tests go to a throw-away directory
define('TEST_UPLOAD_DIR', sys_get_temp_dir() . '/pika-test-uploads-' . getmypid());

/**
 * Integration tests need a MySQL test database. It is created and migrated here; when MySQL is not
 * reachable they are skipped. Safety: a database whose name does not end in "_test" is never touched.
 */
$GLOBALS['PIKA_TEST_DB'] = false;
if (str_ends_with(MYSQL_DB, '_test')) {
    try {
        $target = MYSQL_SOCKET !== '' ? 'unix_socket=' . MYSQL_SOCKET : 'host=' . MYSQL_HOST . ';port=' . MYSQL_PORT;
        $pdo = new PDO("mysql:$target;charset=utf8mb4", MYSQL_USER, MYSQL_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . MYSQL_DB . '` CHARACTER SET utf8mb4');

        ob_start();
        $code = Pika\Console\Commands::run(['pika', 'migrate']);
        ob_end_clean();
        $GLOBALS['PIKA_TEST_DB'] = $code === 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "Integration tests will be skipped: " . $e->getMessage() . "\n");
    }
}
