<?php
/**
 * Lightweight PDO Database Wrapper
 * Supports SQLite with migration capabilities
 */

class DB {
    private $pdo;
    private $config;
    
    public function __construct($config = []) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        $databaseUrl = $this->config['database_url'] ?? null;
        
        if (!$databaseUrl) {
            // Default to SQLite
            $dbPath = $this->config['database_path'] ?? __DIR__ . '/../data/chatbot.db';
            
            // Ensure directory exists
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $databaseUrl = 'sqlite:' . $dbPath;
        }
        
        try {
            $this->pdo = new PDO($databaseUrl);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys for SQLite
            if (strpos($databaseUrl, 'sqlite:') === 0) {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed', 500);
        }
    }
    
    /**
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Database query failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Execute a statement (INSERT, UPDATE, DELETE)
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Execute failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Database execute failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Insert a row and return the last insert ID
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Insert failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            
            // Check for unique constraint violation
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false || 
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception('Duplicate entry: record already exists', 409);
            }
            
            throw new Exception('Database insert failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get a single row
     */
    public function getOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('GetOne failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Database query failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Alias for getOne - Query for a single row
     */
    public function queryOne($sql, $params = []) {
        return $this->getOne($sql, $params);
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Check if a table exists
     */
    public function tableExists($tableName) {
        try {
            // For SQLite
            $databaseUrl = $this->config['database_url'] ?? '';
            if (strpos($databaseUrl, 'sqlite:') === 0 || empty($databaseUrl)) {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$tableName]);
                $result = $stmt->fetch();
                return $result !== false;
            }
            
            // For MySQL/other databases, try a simple query
            $result = $this->pdo->query("SELECT 1 FROM $tableName LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Run migrations from a directory
     */
    public function runMigrations($migrationsDir = null) {
        if (!$migrationsDir) {
            $migrationsDir = __DIR__ . '/../db/migrations';
        }
        
        if (!is_dir($migrationsDir)) {
            throw new Exception('Migrations directory not found: ' . $migrationsDir, 500);
        }
        
        // Create migrations tracking table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL UNIQUE,
                executed_at TEXT NOT NULL
            )
        ");
        
        // Get list of migration files
        $files = glob($migrationsDir . '/*.sql');
        sort($files);
        
        $executedCount = 0;
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Check if migration already executed
            $stmt = $this->pdo->prepare("SELECT filename FROM migrations WHERE filename = ?");
            $stmt->execute([$filename]);
            
            if ($stmt->fetch()) {
                continue; // Skip already executed migrations
            }
            
            // Read and execute migration
            $sql = file_get_contents($file);
            
            try {
                $this->pdo->exec($sql);
                
                // Record migration
                $stmt = $this->pdo->prepare("INSERT INTO migrations (filename, executed_at) VALUES (?, ?)");
                $stmt->execute([$filename, date('Y-m-d H:i:s')]);
                
                $executedCount++;
                error_log("Migration executed: $filename");
            } catch (PDOException $e) {
                error_log("Migration failed: $filename - " . $e->getMessage());
                throw new Exception("Migration failed: $filename - " . $e->getMessage(), 500);
            }
        }
        
        return $executedCount;
    }
    
    /**
     * Get the PDO instance (for advanced usage)
     */
    public function getPDO() {
        return $this->pdo;
    }
}
