<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Basketball Admin (Legacy)</title>
  <!-- Use proxy route path to avoid space encoding issues -->
  <link rel="stylesheet" href="/basketball-admin/style.css">
  <script>window.LEGACY_BASE_PATH = '/basketball-admin/';</script>
</head>
<body>
  {!! $legacy_html !!}
  <!-- Use proxy route path /basketball-admin/app.js instead of asset() -->
  <script src="/basketball-admin/app.js" defer></script>
  <script>
    console.log('[BASKETBALL BLADE] LEGACY_BASE_PATH:', window.LEGACY_BASE_PATH);
    document.addEventListener('DOMContentLoaded', function() {
      console.log('[BASKETBALL BLADE] Page loaded and scripts executed');
    });
  </script>
</body>
</html>
