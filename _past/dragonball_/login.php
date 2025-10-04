<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Login </title>
    <link rel="stylesheet" href="css/login_style.css">
</head>



<body>
    <div class="container">
        <h2 id="form-title">Login</h2>

        <form id="auth-form" action="./utils/login/login.php" method="post">

            <div class="input">
                <label for="id">ID</label>
                <input type="text" id="id" name="id" required>
            </div>

            <div class="input">
                <label for="password">PW</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="input" id="name_input" style="display: none;">
                <label for="name">Name</label>
                <input type="text" id="name" name="name">
            </div>

            <button type="submit" id="submit-button">login</button>
        </form>
        <div class="toggle-mode" id="toggle-auth-mode">Register</div>
    </div>

    <script>
        const toggleAuthMode = document.getElementById('toggle-auth-mode');
        const formTitle = document.getElementById('form-title');
        const authForm = document.getElementById('auth-form');
        const submitButton = document.getElementById('submit-button');
        const nameGroup = document.getElementById('name_input');

        let isLoginMode = true;

        toggleAuthMode.addEventListener('click', () => {
            isLoginMode = !isLoginMode;

            // Login
            if (isLoginMode) {
                formTitle.textContent = 'Login';
                submitButton.textContent = 'Login';
                authForm.action = './utils/login/login.php';
                toggleAuthMode.textContent = 'Register';
                nameGroup.style.display = 'none';
            } 

            // Register
            else {
                formTitle.textContent = 'Sign Up';
                submitButton.textContent = 'Sign Up';
                authForm.action = './utils/login/register.php';
                toggleAuthMode.textContent = 'Login';
                nameGroup.style.display = 'block';
            }
        });

        // To lobby
        authForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(authForm);
            fetch(authForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    window.location.href = './';
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>