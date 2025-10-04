<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title> Login </title>

    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #1e1e1e;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            width: 300px;
        }
        .container h2 {
            margin-bottom: 20px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: #333333;
            color: #ffffff;
            padding: 10px;
            width: 100%;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #444444;
        }
        .toggle-mode {
            margin-top: 10px;
            cursor: pointer;
            color: #aaaaaa;
        }
    </style>
</head>
<body>
    <div class="container">

        <h2 id="form-title">Login</h2>

        <form id="auth-form" action="login_logic.php" method="post">
            <div class="input-group">
                <label for="id">ID</label>
                <input type="text" id="id" name="id" required>
            </div>
            <div class="input-group">
                <label for="password">PW</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group" id="name-group" style="display: none;">
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
        const nameGroup = document.getElementById('name-group');

        let isLoginMode = true;

        toggleAuthMode.addEventListener('click', () => {
            isLoginMode = !isLoginMode;
            if (isLoginMode) {
                formTitle.textContent = 'Login';
                submitButton.textContent = 'Login';
                authForm.action = 'login_logic.php';
                toggleAuthMode.textContent = 'Register';
                nameGroup.style.display = 'none';
            } else {
                formTitle.textContent = 'Sign Up';
                submitButton.textContent = 'Sign Up';
                authForm.action = 'register_logic.php';
                toggleAuthMode.textContent = 'Login';
                nameGroup.style.display = 'block';
            }
        });

        // 로그인 완료 후 로비 페이지로 리다이렉트하는 로직 추가
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
                    window.location.href = '../index.php';
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