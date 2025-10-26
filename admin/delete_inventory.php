<?php
declare(strict_types=1);
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
if (empty($_POST['_csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['_csrf'])) { exit('Invalid CSRF'); }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) exit('Invalid id');

$pdo = new PDO("mysql:host=127.0.0.1;dbname=rb_stores_db;charset=utf8mb4", 'root', '', [
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);

$st = $pdo->prepare("DELETE FROM inventoryitem WHERE ItemID = :id LIMIT 1");
$st->execute([':id'=>$id]);

header('Location: inventory.php?deleted=1');
