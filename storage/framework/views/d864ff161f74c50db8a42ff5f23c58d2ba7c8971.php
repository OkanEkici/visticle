

<?php $__env->startSection('content'); ?>
<div class="app">

<?php echo $__env->make('tenant.general.partials.header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<?php
    $providersSidenav = [
        [
      'name' => 'Plattformübersicht',
      'route' => route('tenant.provider.index', $identifier),
      'iconClass' => 'fas fa-table'
    ]
    ];
    foreach($providers as $provider) {
        $providersSidenav[] = [
            'name' => $provider->name,
            'route' => route('tenant.provider.show', [$identifier, $provider->id, 'general']),
            'iconClass' => 'oi oi-briefcase'
        ];
    }
?>

<?php echo $__env->make('tenant.general.partials.aside',[
  'sidenavItems' => $providersSidenav], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<!-- .app-main -->
<main class="app-main">
<!-- .wrapper -->
<div class="wrapper">
<!-- .page -->
<div class="page">
    <!-- .page-inner -->
    <div class="page-inner">
        <?php echo $__env->make('tenant.components.header', ['title' => 'Plattformverwaltung', 'titleSmall' => 'Insgesamt '.count($providers)], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        
        <!-- .page-section -->
        <div class="page-section">
            <!-- .masonry-layout -->
            <div class="masonry-layout">
                <?php $__currentLoopData = $providers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $provider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <!-- .masonry-item -->
                    <div class="masonry-item col-lg-12">
                        <?php echo $__env->make('tenant.modules.provider.partials.provider-card', [
                            'team' => $provider->name,
                            'providerId' => $provider->id,
                            'description' => $provider->description,
                            'createdAt' => $provider->created_at,
                            'type' => $provider->type()->first()->name,
                            'articleCount' => $provider->articles()->get()->count(),
                            'orderCount' => $provider->orders()->get()->count(),
                            'openOrdersCount' => $provider->orders()->where('fk_order_status_id', '=', '1')->get()->count()
                        ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>    
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

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
  <script src="<?php echo e(asset('js/tenant/modules/provider/provider-module.js')); ?>"></script>
  <script src="<?php echo e(asset('/assets/vendor/masonry-layout/masonry.pkgd.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('tenant.layout.mainlayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/provider/index/provider.blade.php ENDPATH**/ ?>