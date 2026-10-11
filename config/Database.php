<?php
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    // GANTI JADI NAMA DATABASE BARU LU
    private $db_name = "db_pengolahan_nilai"; 
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);
        } catch(Exception $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}
?>