<?php 

    session_start();
    include('config/db.php');

    if (isset($_SESSION['user_login'])) {
        header("location: user.php"); 
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flower_PHP</title>
</head>
<body>
    
</body>
</html>