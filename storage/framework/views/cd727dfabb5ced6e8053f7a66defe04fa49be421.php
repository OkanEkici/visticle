<header class="app-header app-header-dark">
    <!-- .top-bar -->
    <div class="top-bar">
      <!-- .top-bar-brand -->
      <div class="top-bar-brand">
        <!-- toggle aside menu -->
        <button class="hamburger hamburger-squeeze mr-2" type="button" data-toggle="aside-menu" aria-label="toggle aside menu">
          <span class="hamburger-box">
            <span class="hamburger-inner"></span>
          </span>
        </button>
        <!-- /toggle aside menu -->
        <a href="<?php echo e(route('tenant.dashboard', $identifier)); ?>">
        <img src="<?php echo e(asset('assets/img/logo_cutout_nbg_white.png')); ?>" height="32">
        </a>
      </div>
      <!-- /.top-bar-brand -->
  
      <!-- .top-bar-list -->
      <div class="top-bar-list">
        <!-- .top-bar-item -->
        <div class="top-bar-item px-2 d-md-none d-lg-none d-xl-none">
          <!-- toggle menu -->
          <button class="hamburger hamburger-squeeze" type="button" data-toggle="aside" aria-label="toggle menu">
            <span class="hamburger-box">
              <span class="hamburger-inner"></span>
            </span>
          </button>
          <!-- /toggle menu -->
        </div>
        <!-- /.top-bar-item -->
        <!-- .top-bar-item -->
        <div class="top-bar-item top-bar-item-right px-0 d-none d-sm-flex">
          <!-- .nav -->
          <ul class="header-nav nav header-modules">
          <!-- .nav-item Module: Dashboard -->
          <li class="nav-item" id="header-widget-link-dashboard">
            <a class="nav-link" href="/" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-dashboard mr-1"></span>Dashboard
            </a>
          </li>
          <!-- .nav-item Module: Article -->
          <li class="nav-item" id="header-widget-link-article">
            <a class="nav-link" href="<?php echo e(route('tenant.articles.index', $identifier)); ?>" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-cart mr-1"></span>Artikel
            </a>
          </li>
          <!-- .nav-item Module: Orders -->
          <li class="nav-item" id="header-widget-link-order">
            <a class="nav-link" href="<?php echo e(route('tenant.modules.orders.index', $identifier)); ?>" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-task mr-1"></span>Aufträge
            </a>
          </li>
          <!-- .nav-item Module: Kunden -->
          <li class="nav-item" id="header-widget-link-customer">
            <a class="nav-link" href="<?php echo e(route('tenant.modules.customers.index', $identifier)); ?>" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-people mr-1"></span>Kunden
            </a>
          </li>
          <!-- .nav-item Module: Preisgruppen 
          <li class="nav-item" id="header-widget-link-pricegroups">
            <a class="nav-link" href="<?php echo e(route('tenant.modules.pricegroups.index', $identifier)); ?>" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-task mr-1"></span>Preisgruppen
            </a>
          </li>-->
          <!-- .nav-item Module: Orders -->
          <li class="nav-item" id="header-widget-link-provider">
            <a class="nav-link" href="<?php echo e(route('tenant.provider.index', $identifier)); ?>" aria-haspopup="true" aria-expanded="false">
              <span class="oi oi-briefcase mr-1"></span>Plattformverwaltung
            </a>
          </li>
          </ul>
          <!-- /.nav -->
  
          <!-- .btn-account -->
          <div class="dropdown d-flex">
            <button class="btn-account d-none d-md-flex" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span class="user-avatar user-avatar-md">
                <?php if(Auth::user()->teamLogoPath()): ?>
                <img src="<?php echo e(url('storage'.Auth::user()->teamLogoPath())); ?>" alt="">
                <?php else: ?>
                <img src="/assets/img/avatars/unknown-profile.jpg" alt="">
                <?php endif; ?>
                
              </span>
              <span class="account-summary pr-lg-4 d-none d-lg-block">
                <span class="account-name"><?php echo e(isset(Auth::user()->name) ? Auth::user()->name : Auth::user()->email); ?></span>
                <span class="account-description"><?php echo e(Auth::user()->email); ?></span>
              </span>
            </button>
            <!-- .dropdown-menu -->
            <div class="dropdown-menu">
              <div class="dropdown-arrow ml-3"></div>
              <h6 class="dropdown-header d-none d-md-block d-lg-none">Guten Tag</h6>
              <a class="dropdown-item" href="<?php echo e(route('tenant.user.settings', [$identifier, 'general'])); ?>"><span class="dropdown-icon oi oi-cog"></span> Einstellungen</a>
              <a class="dropdown-item" href="<?php echo e(route('tenant.logout', $identifier)); ?>"><span class="dropdown-icon oi oi-account-logout"></span> Logout</a>
            </div>
            <!-- /.dropdown-menu -->
          </div>
          <!-- /.btn-account -->
        </div>
        <!-- /.top-bar-item -->
      </div>
      <!-- /.top-bar-list -->
    </div>
    <!-- /.top-bar -->
  </header>
  <?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/general/partials/header.blade.php ENDPATH**/ ?>