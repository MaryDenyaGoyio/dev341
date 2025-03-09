<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> DEV </title>
    <style>
        .login-container {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1> DEV </h1>

    <?php
        session_start();
        $loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    ?>

    <script>
        function confirmRegister() {
            return confirm("CONFIRM?");
        }
    </script>

    <?php if ($loggedIn): ?>
        <?php if ($_SESSION['uuid']==1): ?>
            <div class="login-container">
                <form action="register.php" method="post" onsubmit="return confirmRegister();">
                    <label for="id">ID:</label>
                    <input type="text" id="id" name="id">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name">
                    <input type="submit" value="Register">
                </form>
            </div>

        <h2>Current Users</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Password</th>
                <th>Name</th>
            </tr>
            <?php
                $mysqli = new mysqli("localhost", "root", "e^ipi=-1", "bmt_awards");

                if ($mysqli -> connect_errno) {
                    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
                    exit();
                }

                $query = "SELECT * FROM users";
                $result = $mysqli->query($query);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr><td>" . $row["id"]. "</td><td>" . $row["password"] . "</td><td>" . $row["name"] . "</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No users found</td></tr>";
                }
                $mysqli->close();
            ?>
        </table>
        
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
