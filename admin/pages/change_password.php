<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/admin_auth.php';

require_admin();
redirect('users.php');
