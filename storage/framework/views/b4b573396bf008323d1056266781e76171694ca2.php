<!-- metric row -->
<div class="metric-row">
    <div class="col-lg-9">
      <div class="metric-row metric-flush">
        <!-- metric column -->
        <div class="col">
          <!-- .metric -->
          <a href="<?php echo e(route('tenant.articles.index', $identifier)); ?>" class="metric metric-bordered align-items-center">
            <h2 class="metric-label">Artikel</h2>
            <p class="metric-value h3">
              <sub><i class="oi oi-cart"></i></sub>
              <span class="value"><?php echo e($articleCount ?? 0); ?></span>
            </p>
          </a>
          <!-- /.metric -->
        </div>
        <!-- /metric column -->
        <!-- metric column -->
        <div class="col">
          <!-- .metric -->
          <a href="<?php echo e(route('tenant.modules.orders.index', $identifier)); ?>" class="metric metric-bordered align-items-center">
            <h2 class="metric-label">Aufträge</h2>
            <p class="metric-value h3">
              <sub><i class="fa fa-tasks"></i></sub>
              <span class="value"><?php echo e($orderCount ?? 0); ?></span>
            </p>
          </a>
          <!-- /.metric -->
        </div>
        <!-- /metric column -->
        <!-- metric column -->
        <div class="col">
          <!-- .metric -->
          <a href="<?php echo e(route('tenant.provider.index', $identifier)); ?>" class="metric metric-bordered align-items-center">
            <h2 class="metric-label">Plattformen</h2>
            <p class="metric-value h3">
              <sub><i class="oi oi-briefcase"></i></sub>
              <span class="value"><?php echo e($providerCount ?? 0); ?></span>
            </p>
          </a>
          <!-- /.metric -->
        </div>
        <!-- /metric column -->
      </div>
    </div>
  
    <!-- metric column -->
    <div class="col-lg-3">
      <!-- .metric -->
      <a href="<?php echo e(route('tenant.modules.orders.index', $identifier)); ?>" class="metric metric-bordered">
        <div class="metric-badge">
          <span class="badge badge-lg badge-success">
            <span class="oi oi-media-record pulse mr-1"></span> Offene Bestellungen
          </span>
        </div>
        <p class="metric-value h3">
          <sub><i class="oi oi-timer"></i></sub>
          <span class="value"><?php echo e($openOrderCount ?? 0); ?></span>
        </p>
      </a>
      <!-- /.metric -->
    </div>
    <!-- /metric column -->
    
  </div>
  <!-- /metric row -->
  <?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/dashboard/partials/metrics.blade.php ENDPATH**/ ?>