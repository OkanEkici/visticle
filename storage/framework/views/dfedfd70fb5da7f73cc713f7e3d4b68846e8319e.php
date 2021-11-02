

<?php $__env->startSection('content'); ?>
<div class="app">

<?php echo $__env->make('tenant.general.partials.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<!-- .app-main -->
<main class="app-main">
<!-- .wrapper -->
<div class="wrapper">
<!-- .page -->
<div class="page">
    <!-- .page-inner -->
    <div class="page-inner">
      <?php echo $__env->make('tenant.components.header', ['title' => 'Hallo, '.(isset(Auth::user()->name) ? Auth::user()->name : Auth::user()->email), 'smallTextUnderTitle' => 'Hier ist die Übersicht von Ihrem Team'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
      <!-- .section-block -->
      <div class="section-block">
        <?php echo $__env->make('tenant.modules.dashboard.partials.teaser', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
      </div>
      <!-- /.section-block -->
      <!-- .section-block -->
      <div class="section-block">
        <?php echo $__env->make('tenant.modules.dashboard.partials.metrics', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <div class="d-flex justify-content-between align-items-center">
          <h1 class="section-title mb-0"></h1>
          <?php echo $__env->make('tenant.components.datepicker', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
      </div>
      <!-- /.section-block -->
      <div class="section-block">
        <?php echo $__env->make('tenant.modules.dashboard.partials.graphs', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
      </div>
      <!-- /.section-block -->

    </div>
    <!-- /.page-inner -->
  </div>
  <!-- /.page -->

</div>
<?php echo $__env->make('tenant.general.partials.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</main>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('custom-scripts'); ?>
  <script src="<?php echo e(asset('/js/tenant/dashboard.js')); ?>"></script>
  <script src="<?php echo e(asset('/assets/vendor/chart.js/Chart.min.js')); ?>"></script>
  <script src="<?php echo e(asset('/assets/javascript/pages/profile-demo.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('tenant.layout.mainlayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/dashboard/index/dashboard.blade.php ENDPATH**/ ?>