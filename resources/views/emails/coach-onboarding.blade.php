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
    <p>Please sign in and change your password at the earliest opportunity.</p>
    <p>For added security, you may use the one-time activation link below to set your password directly.</p>
    <p><a href="{{ $activationUrl }}">Activate Coach Account</a></p>
    <p><a href="{{ $loginUrl }}">Open AC-VMIS Sign-In Page</a></p>
</body>
</html>
