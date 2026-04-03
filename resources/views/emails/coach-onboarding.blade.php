<!DOCTYPE html>
<html>
<head>
    <title>AC-VMIS Coach Account</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Your AC-VMIS coach account has been created by an administrator.</p>
    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
    <p>Please sign in and update your password immediately.</p>
    <p>Preferred secure option: activate your account using this one-time link and set a new password directly.</p>
    <p><a href="{{ $activationUrl }}">Activate Coach Account</a></p>
    <p><a href="{{ $loginUrl }}">Open AC-VMIS Login</a></p>
</body>
</html>
