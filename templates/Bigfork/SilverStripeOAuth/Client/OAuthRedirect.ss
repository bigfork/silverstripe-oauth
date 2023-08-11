<!DOCTYPE html>
<html lang="en">
<head>
    <title>OAuth Redirect</title>
    <meta http-equiv="refresh" content="0;url={$URL.RAW}?code={$Code}&state={$State}" />
</head>
<body>
<h1>You are being redirected, please wait</h1>
<p>If you are not automatically redirected, please <a href="{$URL.RAW}?code={$Code}&state={$State}">click here</a>.</p>
<script type="text/javascript">
    window.setTimeout(function() {
        window.location.href = "{$URL.RAW}?code={$Code}&state={$State}";
    });
</script>
</body>
</html>
