

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
  <?php
      $headerConfig = [
      'title' => 'Artikelverwaltung',
    ];
  /*
    if($isFashioncloudPartner) {
      $headerConfig['titleButtons'] = [
        ['type' => 'link', 'confirm' => true, 'css' => 'btn-secondary', 'text' => 'Mit Fashioncloud synchronisieren', 'href' => route('partner.fashioncloud.syncArticles', $identifier)]
    ];
    }*/
    $Tenant_type = config()->get('tenant.tenant_type');
    if($Tenant_type=='vstcl-industry')
    {
      $headerConfig['primaryOptions'] = [      
          ['type' => 'link','css' => 'btn-outline-primary', 'text' => 'Artikel hinzufügen', 'href' => route('tenant.articles.create', $identifier)],
          ['type' => 'link','css' => 'btn-outline-primary', 'text' => 'Alle', 'href' => route('tenant.articles.index', $identifier)],
          ['type' => 'link','css' => 'btn-outline-primary', 'text' => 'Hauptartikel', 'href' => route('tenant.articles.index_hauptartikel', $identifier)],
          ['type' => 'link','css' => 'btn-outline-primary', 'text' => 'Ersatzteile', 'href' => route('tenant.articles.index_ersatzteile', $identifier)],
          ['type' => 'link', 'css' => 'btn-outline-primary', 'text' => 'Zubehörartikel', 'href' => route('tenant.articles.index_zubehoerartikel', $identifier)]
      ];
    }
  ?>
  <?php echo $__env->make('tenant.components.header', $headerConfig, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
  <div id="indicateLoading" class="spinner-border text-primary" style="width: 3rem; height: 3rem; display: none;" role="status">
    <span class="sr-only">Loading...</span>
  </div>
  <?php
      $bulkOptions = [];
      if($isFashioncloudPartner) {
        $bulkOptions[] = ['id' => 'syncArticlesFashionCloud', 'text' => 'Mit Fashioncloud synchronisieren'];
      }
  ?>
  <?php echo $__env->make('tenant.components.datatable', [
    'title' => 'Übersicht',
    'search' => ['placeholder' => 'Artikel suchen'],
    'firstColumnWidth' => 100,
    'bulkOptions' => $bulkOptions,
    'columns' => [
      'Systemnummer',
      'Artikelnummer',
      'LieferantenNr',
      'Lieferant',
      'Artikelname',
      'Artikelname (Web)',
      'Warengruppennummer',
      'Warengruppenbezeichnung',
      'Wareneigenbezeichnung',
      'Kategorien Onlineshop',
      'Freigegeben',
      'Aktiv',
      'Angelegt am',
      'Aktualisiert am',
      'EANs',
      'Bestand'
    ]
    /*
    'columns' => [
      'Artikelnummer',
      'Artikelname',
      'EAN',
      'Kategorien',
      'Angelegt am',
      'Freigegeben',
      'Aktiv'
    ],*/
  ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

</div>
<!-- /.page-inner -->
</div>
<!-- /.page -->




</div>
<?php echo $__env->make('tenant.general.partials.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</main>
</div>

<script> var typefilter = '<?php echo e((($typefilter == "article")? "hauptartikel": (($typefilter == "spare")? "ersatzteile": (($typefilter == "equipment")? "zubehoerartikel": "" ) ) )); ?>';</script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('custom-css'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/bootstrap-select/css/bootstrap-select.min.css')); ?>"  type="text/css" >
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/flatpickr/flatpickr.min.css')); ?>"  type="text/css" >
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.css')); ?>"  type="text/css" >
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/toastr/toastr.min.css')); ?>"  type="text/css" >
<?php $__env->stopSection(); ?>

<?php $__env->startSection('custom-scripts'); ?>
  <script src="<?php echo e(asset('assets/vendor/bootstrap-select/js/bootstrap-select.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/toastr/toastr.min.js')); ?>"></script>
  <script src="<?php echo e(asset('js/tenant/modules/article/article-module.js')); ?>"></script>
  <script src="<?php echo e(asset('js/tenant/modules/article/articleTable.js')); ?>"></script>
  <script src="<?php echo e(asset('js/tenant/modules/article/articleIndex.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/jquery.dataTables.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/dataTables.buttons.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.bootstrap4.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.html5.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.print.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/datatables/extensions/buttons/buttons.colVis.min.js')); ?>"></script>
  <script src="<?php echo e(asset('js/objects/dataTables.bootstrap.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('tenant.layout.mainlayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/modules/article/index/article.blade.php ENDPATH**/ ?>