<?php

class Database
{
    private static $instance = null;
    private $conn;

    // Private constructor prevents direct object creation
    private function __construct()
    {
        // Load variables from the global $_ENV populated in index.php
        $host = $_ENV['DB_HOST'];
        $db = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];
        $charset = $_ENV['DB_CHARSET'];

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return arrays, not objects
            PDO::ATTR_EMULATE_PREPARES => false, // Native prepared statements (Prevents SQL Injection)
        ];

        try {
            $this->conn = new PDO($dsn, $user, $pass, $options);
        }
        catch (PDOException $e) {
            // In production, log this to a file instead of displaying it
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    // The Singleton method
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    // Prevent cloning of the instance
    private function __clone()
    {
    }
    // Prevent unserializing of the instance
    public function __wakeup()
    {
    }
}