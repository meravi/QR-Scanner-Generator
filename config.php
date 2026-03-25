<?php

mysqli_report(MYSQLI_REPORT_OFF);

class RaviKoQr
{
    private $conn;
    private $lastError = '';
    private $config = array(
        'host' => '127.0.0.1',
        'user' => 'root',
        'pass' => '',
        'name' => 'qrcode',
    );

    public function __construct(array $config = array())
    {
        $envConfig = array(
            'host' => getenv('QR_DB_HOST') ?: $this->config['host'],
            'user' => getenv('QR_DB_USER') ?: $this->config['user'],
            'pass' => getenv('QR_DB_PASS') !== false ? getenv('QR_DB_PASS') : $this->config['pass'],
            'name' => getenv('QR_DB_NAME') ?: $this->config['name'],
        );

        $this->config = array_merge($this->config, $envConfig, $config);
        $this->connect();
    }

    private function connect()
    {
        $connection = @new mysqli(
            $this->config['host'],
            $this->config['user'],
            $this->config['pass'],
            $this->config['name']
        );

        if ($connection instanceof mysqli && !$connection->connect_error) {
            $connection->set_charset('utf8mb4');
            $this->conn = $connection;

            return;
        }

        $this->conn = null;
        $this->lastError = $connection instanceof mysqli && $connection->connect_error
            ? $connection->connect_error
            : 'Unable to connect to the database.';
    }

    public function isConnected()
    {
        return $this->conn instanceof mysqli;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function insertQr($qrUserName, $qrContent, $qrImage, $qrLink)
    {
        if (!$this->isConnected()) {
            $this->lastError = 'Database archive is unavailable. The image was generated locally only.';

            return false;
        }

        $statement = $this->conn->prepare(
            'INSERT INTO qrcodes (qrUsername, qrContent, qrImg, qrlink) VALUES (?, ?, ?, ?)'
        );

        if (!$statement) {
            $this->lastError = $this->conn->error ?: 'Unable to prepare the insert query.';

            return false;
        }

        $statement->bind_param('ssss', $qrUserName, $qrContent, $qrImage, $qrLink);
        $result = $statement->execute();

        if (!$result) {
            $this->lastError = $statement->error ?: 'Unable to save the QR record.';
        }

        $statement->close();

        return (bool) $result;
    }

    public function getRecentQrs($limit = 8)
    {
        if (!$this->isConnected()) {
            return array();
        }

        $limit = max(1, min(24, (int) $limit));
        $query = sprintf(
            'SELECT id, qrUsername, qrContent, qrImg, qrlink FROM qrcodes ORDER BY id DESC LIMIT %d',
            $limit
        );
        $result = $this->conn->query($query);

        if (!$result) {
            $this->lastError = $this->conn->error ?: 'Unable to load the recent QR history.';

            return array();
        }

        $rows = array();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();

        return $rows;
    }
}

$meravi = new RaviKoQr();
