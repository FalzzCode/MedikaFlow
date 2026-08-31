<?php
require_once __DIR__ . '/../includes/functions.php';
redirect_to('auth/logout.php?csrf_token=' . rawurlencode(csrf_token()));
