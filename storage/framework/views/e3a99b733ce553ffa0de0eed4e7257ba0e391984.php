

<?php $__env->startSection('content'); ?>
<div class="app">

<?php echo $__env->make('tenant.general.partials.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php echo $__env->make('tenant.general.partials.aside',[
  'sidenavItems' => $sideNavConfig
  ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<!-- .app-main -->
<main class="app-main">
<!-- .wrapper -->
<div class="wrapper">
<!-- .page -->
<div class="page">
    <!-- .page-inner -->
    <div class="page-inner">
        <?php echo $__env->make('tenant.components.header', ['title' => 'Aufträge'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
      <?php echo $__env->make('tenant.components.datatable', [
        'firstColumnWidth' => 200,
        'title' => 'Übersicht',
        'search' => ['placeholder' => 'Auftrag suchen'],
        'tabs' => array_merge([['name' => 'Alle', 'cssClasses' => 'active']],$tabs),
        'columns' => [
          'Auftrag',
          'Angelegt am',
          'Status',
          'Vor- und Nachname',
          'Zahlungsart',
          'Plattform',
          'Versandart',
          'Zalando Store ID',
        ],
      ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    </div>
    <!-- /.page-inner -->
  </div>
  <!-- /.page -->

</div>
<?php echo $__env->make('tenant.general.partials.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</main>
</div>
<script type="text/javascript">var TABLE_ROUTE="/orders";</script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('custom-css'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.css')); ?>"  type="text/css" >
<?php $__env->stopSection(); ?>

<?php $__env->startSection('custom-scripts'); ?>
  <script src="<?php echo e(asset('js/tenant/modules/order/order-module.js')); ?>"></script>
  <script src="<?php echo e(asset('js/tenant/modules/order/orderTable.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/jquery.dataTables.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/dataTables.buttons.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.html5.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.print.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.colVis.min.js')); ?>"></script>
  <script src="<?php echo e(asset('js/objects/dataTables.bootstrap.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('tenant.layout.mainlayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/order/index/order.blade.php ENDPATH**/ ?>