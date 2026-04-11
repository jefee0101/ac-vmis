<!DOCTYPE html>
<html>
<head>
    <title>AC-VMIS Admin Invitation</title>
</head>
<body>
    <h1>Administrator Invitation</h1>
    <p>Hello,</p>
    <p>{{ $sender->name }} invited you to create an administrator account for AC-VMIS.</p>
    <p>This invitation is intended for <strong>{{ $invite->email }}</strong> and expires on {{ $invite->expires_at->format('M d, Y h:i A') }}.</p>
    <p>
        <a href="{{ $acceptUrl }}">Accept the admin invitation</a>
    </p>
    <p>If you were not expecting this invitation, you can safely ignore this email.</p>
</body>
</html>
