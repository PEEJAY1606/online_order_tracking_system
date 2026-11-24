
<?php

$host = "sql110.infinityfree.com";
$dbname = "if0_43496747_online_order_tracking";
$username = "if0_43496747";
$password = "VinuyaPeter0616";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die("Database Connection Failed: " . $e->getMessage());

}
