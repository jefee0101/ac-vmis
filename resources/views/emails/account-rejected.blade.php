<!DOCTYPE html>
<html>
<head>
    <title>Account Rejected</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Unfortunately, your account has been rejected by the admin.</p>

    @if($remarks)
        <p><strong>Remarks:</strong> {{ $remarks }}</p>
    @endif

    <p>If you believe this is an error, please contact support.</p>
</body>
</html>
