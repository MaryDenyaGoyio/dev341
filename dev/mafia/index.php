<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>야스코피아</title>
</head>

<?php

?>

<body>
  <h1>야스코피아 로비</h1>

  <!-- 로그인 부분 -->
  <?php if (isset($_SESSION['uuid'])): ?>
    <a href="stats.php"><?php echo htmlspecialchars($_SESSION['name']); ?></a>
  <?php else: ?>
    <a href="auth/auth.php">sign in/up</a>
  <?php endif; ?>

</body>
</html>