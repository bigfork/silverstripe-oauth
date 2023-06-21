<!doctype html>
<html lang="en">
<head>
    <title>OAuth Redirect</title>
    <style>
        body {
            padding: 23% 0;
            text-align: center;
        }
    </style>
</head>
<body>
<h3>One moment please you will be redirected.</h3>
<p>If you are not automatically redirected, click <a href="$url.RAW">here</a>.</p>
<script type="text/javascript">
    window.setTimeout(() => window.location.href = "$url.RAW");
</script>
</body>
</html>
