<?

// database
$data = file("/database/password/file", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
try {
    $dbh = new PDO("mysql:host=localhost;dbname=scaanner", $data[1], $data[2],
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"));
} catch (Exception $e) {
    throw new \Exception('Database connect failed');
}
unset($data);
