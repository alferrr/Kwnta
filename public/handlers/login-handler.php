<?php

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/controllers/AuthController.php';

$controller = new AuthController();
$controller->login($conn);
