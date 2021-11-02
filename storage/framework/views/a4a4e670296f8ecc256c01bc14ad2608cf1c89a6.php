<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Visticle</title>
<link rel="shortcut icon" type="image/x-icon" href="/favicon.png"/>
<!-- BEGIN PLUGINS STYLES -->
<link rel="stylesheet" href="assets/vendor/open-iconic/css/open-iconic-bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.css">
<!-- BEGIN THEME STYLES -->
<link href="<?php echo e(asset('css/app.css')); ?>" rel="stylesheet" type="text/css"  data-skin="default">
<link href="<?php echo e(asset('css/app-dark.css')); ?>" rel="stylesheet" type="text/css"  data-skin="dark">
<script>
    var skin = localStorage.getItem('skin') || 'default';
    var isCompact = JSON.parse(localStorage.getItem('hasCompactMenu'));
    var disabledSkinStylesheet = document.querySelector('link[data-skin]:not([data-skin="'+ skin +'"])');
  
    // Disable unused skin immediately
    disabledSkinStylesheet.setAttribute('rel', '');
    disabledSkinStylesheet.setAttribute('disabled', true);
  
    // add flag class to html immediately
    if (isCompact == true) document.querySelector('html').classList.add('preparing-compact-menu');
  </script>
  <!-- END THEME STYLES --><?php /**PATH C:\xampp\htdocs\visticle\resources\views/layout/partials/head.blade.php ENDPATH**/ ?>