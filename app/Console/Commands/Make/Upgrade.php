<?php

declare(strict_types=1);

namespace App\Console\Commands\Make;

use File;
use Illuminate\Console\Command;

class Upgrade extends Command
{
    protected $signature   = 'make:upgrade {version}';
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $version      = $this->argument('version');
        $versionUpper = strtoupper($version);

        if (preg_match('/V(\d+\.){2}\d+/', $versionUpper) === 0) {
            $this->error('Please enter the correct version number, for example v6.0.0');

            return;
        }

        $versionString      = str_replace('.', '_', $versionUpper);
        $upgradeCommandFile = app_path('Console/Commands/Upgrade/') . $versionString . '.php';

        if (File::missing($upgradeCommandFile)) {
            $upgradeCommandContent = <<<PHP
<?php

namespace App\\Console\\Commands\\Upgrade;

use Illuminate\\Console\\Command;

class $versionString extends Command
{
    protected \$signature   = 'upgrade:$version';
    protected \$description = 'Upgrade to $version';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

    }
}

PHP;
            File::put($upgradeCommandFile, $upgradeCommandContent);
            $this->info("Generate $upgradeCommandFile completed.");
        }

        $testPath = base_path("tests/Commands/Upgrade/$versionString/");
        $testFile = $testPath . 'CommandTest.php';

        if (File::missing($testPath)) {
            File::makeDirectory($testPath);
        }

        if (File::missing($testFile)) {
            $testContent = <<<PHP
<?php

namespace Tests\\Commands\\Upgrade\\$versionString;

use Artisan;

class CommandTest extends \\Tests\\Commands\\Upgrade\\TestCase
{
    public function testCommand()
    {
        Artisan::call('upgrade:$version');
    }
}

PHP;
            File::put($testFile, $testContent);
            $this->info("Generate $testFile completed.");
        }

        $testUpgradeFile = $testPath . 'UpgradeTest.php';

        if (File::missing($testUpgradeFile)) {
            $testUpgradeContent = <<<PHP
<?php

namespace Tests\\Commands\\Upgrade\\$versionString;

use Artisan;

class UpgradeTest extends \\Tests\\Commands\\Upgrade\\TestCase
{
    public function testUpgrade()
    {
        \$this->artisan('bjyblog:update')->assertExitCode(0);
    }
}

PHP;
            File::put($testUpgradeFile, $testUpgradeContent);
            $this->info("Generate $testUpgradeFile completed.");
        }

        $PreviousVersion = trim(shell_exec('git tag --sort=-v:refname | head -n 1'));

        // Migrations
        $databasePath      = 'database/';
        $testMigrationPath = $testPath . 'migrations';
        File::moveDirectory(database_path('migrations'), $testMigrationPath, true);
        File::deleteDirectory($databasePath, true);
        shell_exec("git checkout $PreviousVersion -- $databasePath/migrations");
        File::copyDirectory(database_path('migrations'), $testMigrationPath);
        File::deleteDirectory($databasePath, true);

        // Seeds
        shell_exec("git checkout $PreviousVersion -- $databasePath/seeds");
        $testSeedPath = $testPath . 'seeds';
        File::moveDirectory(database_path('seeds'), $testSeedPath, true);
        File::deleteDirectory($databasePath, true);

        shell_exec("git checkout HEAD -- $databasePath");
        $testMigrationFiles = File::files($testMigrationPath);

        foreach ($testMigrationFiles as $testMigrationFile) {
            File::put(
                $testMigrationFile->getPathname(),
                str_replace([
                    "declare(strict_types=1);\n\n",
                    'Schema::',
                ], [
                    "declare(strict_types=1);\n\nnamespace Tests\\Commands\\Upgrade\\$versionString\\Migrations;\n\n",
                    '\\Schema::',
                ],
                    File::get($testMigrationFile->getPathname())
                )
            );
            $this->info('Generate ' . $testMigrationFile->getFilename() . ' completed.');
        }

        $testSeedFiles = File::files($testSeedPath);

        foreach ($testSeedFiles as $testSeedFile) {
            File::put(
                $testSeedFile->getPathname(),
                str_replace([
                    "declare(strict_types=1);\n\n",
                    ' DB',
                ], [
                    "declare(strict_types=1);\n\nnamespace Tests\\Commands\\Upgrade\\$versionString\\Seeds;\n\n",
                    ' \\DB',
                ],
                    File::get($testSeedFile->getPathname())
                )
            );
            $this->info('Generate ' . $testSeedFile->getFilename() . ' completed.');
        }

        shell_exec('composer dump-autoload');
    }
}
