<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <?php echo $__env->make('tenant.layout.partials.head', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->yieldContent('custom-css'); ?>
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
        <!-- END THEME STYLES -->
    </head>
    <body>
        <?php echo $__env->yieldContent('content'); ?>
        <?php echo $__env->make('tenant.layout.partials.script', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->yieldContent('custom-scripts'); ?>
    </body>
</html><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/layout/mainlayout.blade.php ENDPATH**/ ?>