<?php
    $mysqli = new mysqli("localhost", "root", "e^ipi=-1", "bmt_awards");

    if ($mysqli -> connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        $password = $_POST['password'];
        $name = $_POST['name'];

        $query = "INSERT INTO users (id, password, name) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sss", $id, $password, $name);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: http://124.50.137.165/dev/");
    $mysqli->close();
?>