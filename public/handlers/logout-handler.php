<?php
// public/handlers/logout-handler.php
session_start();
session_destroy();
header('Location: /index.php');
exit();
