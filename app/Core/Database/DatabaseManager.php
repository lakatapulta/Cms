<?php

namespace FlexCMS\Core\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use FlexCMS\Core\Application;

class DatabaseManager
{
    /**
     * Database capsule
     */
    protected $capsule;

    /**
     * Application instance
     */
    protected $app;

    /**
     * Boot status
     */
    protected $booted = false;

    /**
     * Create database manager
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->capsule = new Capsule;
    }

    /**
     * Boot database
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->setupConnections();
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->booted = true;
    }

    /**
     * Setup database connections
     */
    protected function setupConnections()
    {
        $connections = config('database.connections', []);
        $default = config('database.default', 'mysql');

        foreach ($connections as $name => $config) {
            $this->capsule->addConnection($config, $name);
        }

        if (isset($connections[$default])) {
            $this->capsule->addConnection($connections[$default]);
        }
    }

    /**
     * Get schema builder
     */
    public function schema($connection = null)
    {
        return $this->capsule->schema($connection);
    }

    /**
     * Get database connection
     */
    public function connection($name = null)
    {
        return $this->capsule->connection($name);
    }

    /**
     * Run migrations
     */
    public function migrate()
    {
        $this->createMigrationsTable();
        $this->runCoreMigrations();
        $this->runModuleMigrations();
    }

    /**
     * Create migrations table
     */
    protected function createMigrationsTable()
    {
        if (!$this->schema()->hasTable('migrations')) {
            $this->schema()->create('migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Run core migrations
     */
    protected function runCoreMigrations()
    {
        $migrations = [
            'CreateUsersTable',
            'CreatePostsTable',
            'CreatePagesTable',
            'CreateCategoriesTable',
            'CreateTagsTable',
            'CreateSettingsTable',
            'CreateMenusTable',
        ];

        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'core');
        }
    }

    /**
     * Run module migrations
     */
    protected function runModuleMigrations()
    {
        $moduleManager = $this->app->get('modules');
        $modules = $moduleManager->getActiveModules();

        foreach ($modules as $module) {
            $migrationsPath = module_path($module, 'migrations');
            if (is_dir($migrationsPath)) {
                $migrationFiles = glob($migrationsPath . '/*.php');
                foreach ($migrationFiles as $file) {
                    $migrationClass = pathinfo($file, PATHINFO_FILENAME);
                    $this->runMigration($migrationClass, $module);
                }
            }
        }
    }

    /**
     * Run single migration
     */
    protected function runMigration($migrationClass, $module = 'core')
    {
        $migrationName = $module . '_' . $migrationClass;
        
        // Check if migration already ran
        $exists = $this->connection()
            ->table('migrations')
            ->where('migration', $migrationName)
            ->exists();

        if ($exists) {
            return;
        }

        // Run migration
        $migrationFile = $module === 'core' 
            ? __DIR__ . '/Migrations/' . $migrationClass . '.php'
            : module_path($module, 'migrations/' . $migrationClass . '.php');

        if (file_exists($migrationFile)) {
            require_once $migrationFile;
            
            if (class_exists($migrationClass)) {
                $migration = new $migrationClass;
                $migration->up();

                // Record migration
                $this->connection()
                    ->table('migrations')
                    ->insert([
                        'migration' => $migrationName,
                        'batch' => $this->getNextBatchNumber(),
                    ]);
            }
        }
    }

    /**
     * Get next batch number
     */
    protected function getNextBatchNumber()
    {
        $lastBatch = $this->connection()
            ->table('migrations')
            ->max('batch');

        return $lastBatch ? $lastBatch + 1 : 1;
    }

    /**
     * Rollback migrations
     */
    public function rollback($steps = 1)
    {
        $batches = $this->connection()
            ->table('migrations')
            ->orderBy('batch', 'desc')
            ->limit($steps)
            ->pluck('batch')
            ->unique();

        foreach ($batches as $batch) {
            $migrations = $this->connection()
                ->table('migrations')
                ->where('batch', $batch)
                ->orderBy('migration', 'desc')
                ->get();

            foreach ($migrations as $migration) {
                $this->rollbackMigration($migration->migration);
            }
        }
    }

    /**
     * Rollback single migration
     */
    protected function rollbackMigration($migrationName)
    {
        list($module, $migrationClass) = explode('_', $migrationName, 2);
        
        $migrationFile = $module === 'core' 
            ? __DIR__ . '/Migrations/' . $migrationClass . '.php'
            : module_path($module, 'migrations/' . $migrationClass . '.php');

        if (file_exists($migrationFile)) {
            require_once $migrationFile;
            
            if (class_exists($migrationClass)) {
                $migration = new $migrationClass;
                
                if (method_exists($migration, 'down')) {
                    $migration->down();
                }

                // Remove migration record
                $this->connection()
                    ->table('migrations')
                    ->where('migration', $migrationName)
                    ->delete();
            }
        }
    }

    /**
     * Get capsule instance
     */
    public function getCapsule()
    {
        return $this->capsule;
    }
}