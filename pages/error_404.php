<?php
http_response_code(404);
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZPGC Services | Page not found</title>
    <style>
        body { font-family: Inter, Segoe UI, sans-serif; background: #f4f4f5; color: #1a1a1a; margin: 0; }
        main { max-width: 480px; margin: 12vh auto; padding: 32px; background: #fff; border-radius: 12px; }
        h1 { color: #610107; font-size: 22px; margin: 0 0 8px; }
        p { color: #5a5a5a; line-height: 1.5; }
        a { color: #610107; font-weight: 600; }
    </style>
</head>
<body>
    <main>
        <h1>Page not found</h1>
        <p>That address is not part of ZPGC Services.</p>
        <p><a href="login_signup.php">Return to sign in</a></p>
    </main>
</body>
</html>
