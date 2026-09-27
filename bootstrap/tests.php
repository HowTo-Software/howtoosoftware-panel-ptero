<?php

use Illuminate\Support\Str;
use NunoMaduro\Collision\Provider;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/app.php';

/** @var Pterodactyl\Console\Kernel $kernel */
$kernel = $app->make(Kernel::class);

/*
 * Bootstrap the kernel and prepare application for testing.
 */
$kernel->bootstrap();

// Register the collision service provider so that errors during the test
// setup process are output nicely.
(new Provider())->register();

$output = new ConsoleOutput();

$prefix = 'database.connections.' . config('database.default');
if (!Str::contains(config("$prefix.database"), 'test')) {
    $output->writeln(PHP_EOL . '<error>Cannot run test process against non-testing database.</error>');
    $output->writeln(PHP_EOL . '<error>Environment is currently pointed at: "' . config("$prefix.database") . '".</error>');
    exit(1);
}

/*
 * Perform database migrations and reseeding before continuing with
 * running the tests.
 */
if (!env('SKIP_MIGRATIONS')) {
    $output->writeln(PHP_EOL . '<info>Refreshing database for Integration tests...</info>');
    $kernel->call('migrate:fresh');

    $output->writeln('<info>Seeding database for Integration tests...</info>' . PHP_EOL);
    $kernel->call('db:seed');
} else {
    $output->writeln(PHP_EOL . '<comment>Skipping database migrations...</comment>' . PHP_EOL);
}

/*
 * Bootstrapping the kernel above left Laravel's error and exception handlers installed
 * process-wide. PHPUnit snapshots the handler stack before every test and restores it
 * afterwards, while Laravel's own teardown unwinds it, so each test gets reported risky
 * for "removing handlers other than its own". Every test boots its own application, so
 * nothing past this point needs the ones registered here.
 */
$unwindHandlers = static function (): void {
    while (true) {
        $previous = set_error_handler(static fn () => false);
        restore_error_handler();

        if ($previous === null) {
            break;
        }

        restore_error_handler();
    }

    while (true) {
        $previous = set_exception_handler(static fn () => null);
        restore_exception_handler();

        if ($previous === null) {
            break;
        }

        restore_exception_handler();
    }
};

$unwindHandlers();
