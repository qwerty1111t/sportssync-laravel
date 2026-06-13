<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Volleyball Admin (Legacy)</title>
  <!-- Use proxy route path to avoid space encoding issues -->
  <link rel="stylesheet" href="/volleyball-admin/volleyball_admin.css">
  <script>window.LEGACY_BASE_PATH = '/volleyball-admin/';</script>
</head>
<body>
  {!! $legacy_html !!}
  <!-- Use proxy route path /volleyball-admin/volleyball_app.js instead of asset() -->
  <script src="/volleyball-admin/volleyball_app.js" defer></script>
  <script>
    console.log('[VOLLEYBALL BLADE] LEGACY_BASE_PATH:', window.LEGACY_BASE_PATH);
    document.addEventListener('DOMContentLoaded', function() {
      console.log('[VOLLEYBALL BLADE] Page loaded and scripts executed');
    });
  </script>
</body>
</html>
