<!-- metric row -->
<div class="metric-row">
    <!-- metric column -->
    <div class="col">
      <!-- .metric -->
      <a href="<?php echo e(route('tenant.articles.index', $identifier)); ?>" class="metric metric-bordered align-items-center bg-primary text-white" >

        <p class="metric-value h6">
          <span class="value">Artikelverwaltung</span>
        </p>
        <div class="mt-2"><p>Verwalten Sie hier Ihren Artikelkatalog.</p></div>
        </a>
      <!-- /.metric -->
    </div>
    <!-- /metric column -->
    <!-- metric column -->
    <div class="col">
      <!-- .metric -->
      <a href="<?php echo e(route('tenant.modules.orders.index', $identifier)); ?>" class="metric metric-bordered align-items-center bg-primary text-white">
        <p class="metric-value h6">
          <span class="value">Auftragsverwaltung</span>
        </p>
        <div class="mt-2"><p>Verwalten und verfolgen Sie hier Ihre Aufträge</p></div>
        </a>
      <!-- /.metric -->
    </div>
    <!-- /metric column -->
    <!-- metric column -->
    <div class="col">
      <!-- .metric -->
      <a href="<?php echo e(route('tenant.provider.index', $identifier)); ?>" class="metric metric-bordered align-items-center bg-primary text-white">
        <p class="metric-value h6">
          <span class="value">Plattformverwaltung</span>
        </p>
        <div class="mt-2"><p>Verwalten Sie hier Ihre angeschlossenen Plattformen</p></div>
        </a>
      <!-- /.metric -->
    </div>
    <!-- /metric column -->
    <!-- metric column -->
    <div class="col">
      <!-- .metric -->
      <a href="<?php echo e(route('tenant.user.settings', [$identifier, 'general'])); ?>" class="metric metric-bordered align-items-center bg-primary text-white">
        <p class="metric-value h6">
          <span class="value">Einstellungen</span>
        </p>
        <div class="mt-2"><p>Hier können Sie Einstellungen für Ihr Team machen</p></div>
        </a>
      <!-- /.metric -->
    </div>
    <!-- /metric column -->
    
  </div>
  <!-- /metric row -->
  <?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/dashboard/partials/teaser.blade.php ENDPATH**/ ?>