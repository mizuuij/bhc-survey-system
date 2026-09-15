<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

unset($_SESSION['admin_id']);
redirect('../resident/login.php');