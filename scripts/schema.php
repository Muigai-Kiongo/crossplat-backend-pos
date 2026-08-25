<?php
require 'app/config/database.php';
$c = require 'app/config/database.php';
$pdo = new PDO('pgsql:host='.$c['host'].';dbname='.$c['dbname'].';port='.($c['port']??'5432'), $c['username'], $c['password']);
print_r($pdo->query('DESCRIBE sales')->fetchAll(PDO::FETCH_COLUMN));
print_r($pdo->query('DESCRIBE orders')->fetchAll(PDO::FETCH_COLUMN));
