<aside class="app-aside app-aside-expand-md app-aside-light">
    <!-- .aside-content -->
    <div class="aside-content">
      <!-- .aside-header -->
      <header class="aside-header d-block d-md-none">
        <!-- .btn-account -->
        <button class="btn-account" type="button" data-toggle="collapse" data-target="#dropdown-aside">
          <span class="user-avatar user-avatar-lg">
            <img src="/assets/img/avatars/unknown-profile.jpg" alt="">
          </span>
          <span class="account-icon">
            <span class="fa fa-caret-down fa-lg"></span>
          </span>
          <span class="account-summary">
            <span class="account-name"><?php echo e(isset(Auth::user()->name) ? Auth::user()->name : Auth::user()->email); ?></span>
            <span class="account-description"><?php echo e(Auth::user()->email); ?></span>
          </span>
        </button>
        <!-- /.btn-account -->
        <!-- .dropdown-aside -->
        <div id="dropdown-aside" class="dropdown-aside collapse">
          <!-- dropdown-items -->
          <div class="pb-3">
            <a class="dropdown-item" href="user-profile.html"><span class="dropdown-icon oi oi-cog"></span> Einstellungen</a>
            <a class="dropdown-item" href="<?php echo e(route('tenant.logout', $identifier)); ?>"><span class="dropdown-icon oi oi-account-logout"></span> Logout</a>
            <!--<div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#">Hilfe</a>-->
          </div>
          <!-- /dropdown-items -->
        </div>
        <!-- /.dropdown-aside -->
      </header>
      <!-- /.aside-header -->
      <!-- .aside-menu -->
      <div class="aside-menu overflow-hidden">
        <!-- .stacked-menu -->
        <nav id="stacked-menu" class="stacked-menu">
          <!-- .menu -->
          <ul class="menu">
            <?php if(isset($sidenavItems)): ?>
            <?php $__currentLoopData = $sidenavItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sidenavItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(isset($sidenavItem['type']) && $sidenavItem['type'] == 'button'): ?>
                <a  href="<?php echo e($sidenavItem['route']); ?>" class="btn btn-light text-primary btn-block">
                  <span class="d-compact-menu-none"><?php echo e($sidenavItem['name']); ?></span>
                  <i class="<?php echo e($sidenavItem['iconClass']); ?> ml-1"></i>
                </a>
                <div class="border-bottom p-2 mb-2"></div>
              <?php else: ?>
                <li class="menu-item <?php echo e(((isset($sidenavItem['isActive']) && $sidenavItem['isActive'] == true) ? 'has-active' : '')); ?>">
                  <a class="menu-link" href="<?php echo e($sidenavItem['route']); ?>">
                    <span class="menu-icon <?php echo e($sidenavItem['iconClass']); ?>"></span>
                    <span class="menu-text"><?php echo e($sidenavItem['name']); ?></span>
                </a>
              </li>
              <?php endif; ?>
              
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
          </ul>
          <!-- /.menu -->
        </nav>
        <!-- /.stacked-menu -->
      </div>
      <!-- /.aside-menu -->
    <!-- Skin changer -->
    <footer class="aside-footer border-top p-2">
        <button class="btn btn-light btn-block text-primary" data-toggle="skin">
          <span class="d-compact-menu-none">Nachtmodus</span>
          <i class="fas fa-moon ml-1"></i>
        </button>
      </footer>
      <!-- /Skin changer -->
    </div>
    <!-- /.aside-content -->
  </aside>
  <?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/general/partials/aside.blade.php ENDPATH**/ ?>