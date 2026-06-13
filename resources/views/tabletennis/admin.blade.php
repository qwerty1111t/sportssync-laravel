<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Table Tennis Admin (Legacy)</title>
  <!-- Use proxy route path to avoid space encoding issues -->
  <link rel="stylesheet" href="/tabletennis-admin/tabletennis_admin.css">
  <script>window.LEGACY_BASE_PATH = '/tabletennis-admin/';</script>
</head>
<body>
  {!! $legacy_html !!}
  <!-- Use proxy route path /tabletennis-admin/tabletennis_admin.js instead of asset() -->
  <script src="/tabletennis-admin/tabletennis_admin.js" defer></script>
  <script>
    console.log('[TABLE TENNIS BLADE] LEGACY_BASE_PATH:', window.LEGACY_BASE_PATH);
    document.addEventListener('DOMContentLoaded', function() {
      console.log('[TABLE TENNIS BLADE] Page loaded and scripts executed');
    });
  </script>
</body>
</html>
