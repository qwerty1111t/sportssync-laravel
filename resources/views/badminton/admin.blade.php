<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Badminton Admin (Legacy)</title>
    <!-- Use proxy route path to avoid space encoding issues -->
    <link rel="stylesheet" href="/badminton-admin/badminton_admin.css">
    <script>window.LEGACY_BASE_PATH = '/badminton-admin/';</script>
    <style>/* small wrapper to ensure full-width */ body { background: #111; }</style>
</head>
<body>
{!! $legacy_html !!}
<!-- Use proxy route path /badminton-admin/badminton_admin.js instead of asset() -->
<script src="/badminton-admin/badminton_admin.js" defer></script>
<script>
  console.log('[BADMINTON BLADE] LEGACY_BASE_PATH:', window.LEGACY_BASE_PATH);
  document.addEventListener('DOMContentLoaded', function() {
    console.log('[BADMINTON BLADE] Page loaded and scripts executed');
  });
</script>
</body>
</html>
