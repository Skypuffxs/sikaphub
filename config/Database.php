<?php

class Database
{
    private static $instance = null;
    private $conn = null;

    private function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, SESSION wait_timeout = 28800, SESSION interactive_timeout = 28800",
        ];

        try {
            $this->conn = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        if ($this->conn === null) {
            $this->connect();
        } else {
            try {
                // Ping connection to verify server hasn't dropped it during long operations
                $this->conn->query("SELECT 1");
            } catch (\Throwable $e) {
                // Connection died (e.g. 2006 MySQL server has gone away) — reconnect automatically
                $this->connect();
            }
        }
        return $this->conn;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }
}