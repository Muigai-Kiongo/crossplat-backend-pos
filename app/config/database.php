
<?php

// app/config/database.php

return [
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => getenv('DB_PORT') ?: '5432',
    'dbname' => getenv('DB_DATABASE') ?: 'mkon_pos',
    'username' => getenv('DB_USERNAME') ?: 'pos_admin',
    'password' => getenv('DB_PASSWORD') ?: 'secretpassword',
    'charset' => 'utf8mb4'
];


?>


