<?php


session_start();
include(__DIR__ . "/../../config/db.php");
include(__DIR__ . "/../../src/controllers/AuthController.php");

$controller = new AuthController();
$controller->register($conn);
