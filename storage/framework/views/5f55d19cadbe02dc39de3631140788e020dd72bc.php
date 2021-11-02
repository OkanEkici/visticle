    <!-- .page-title-bar -->
    <header class="page-title-bar">

        <?php if(isset($floatingActionButton)): ?>
        <!-- floating action -->
        <a href="<?php echo e($floatingActionButton['link']); ?>"><button type="button" class="btn btn-success btn-floated"><span class="fa <?php echo e($floatingActionButton['icon'] ?? 'fa-plus'); ?>"></span></button></a>
        <!-- /floating action -->
        <?php endif; ?>


        <!-- title and toolbar -->
        <div class="d-md-flex align-items-md-start">
          <?php if(isset($smallTextUnderTitle)): ?>
          <p class="lead">
            <span class="font-weight-bold"><?php echo e($title); ?></span>
            <span class="d-block text-muted"><?php echo e($smallTextUnderTitle); ?></span>
          </p>
          <?php else: ?>
            <h1 class="page-title mr-sm-auto"><?php echo e($title); ?>

              <?php if(isset($titleSmall)): ?>
              <small class="badge"><?php echo e($titleSmall); ?></small>
              <?php endif; ?>
              <?php if(isset($primaryOptions)): ?>
              <?php $__currentLoopData = $primaryOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $primaryOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(empty($primaryOption)): ?>
                <?php continue; ?>;
                <?php endif; ?>
                <?php switch($primaryOption['type']):
                    case ('link'): ?>
                        <a <?php echo (isset($primaryOption['confirm'])?'onclick="return confirm(\'Sind Sie sicher?\')"':''); ?> href="<?php echo e($primaryOption['href']); ?>"><button type="button" class="btn <?php echo e($primaryOption['css'] ?? ''); ?>"><?php echo e($primaryOption['text']); ?></button></a>
                        <?php break; ?>
                    <?php default: ?>
                        <button id="<?php echo e($primaryOption['id']); ?>" <?php echo (isset($primaryOption['confirm'])?'onclick="return confirm(\'Sind Sie sicher?\')"':''); ?> <?php echo e((isset($primaryOption['attributes']) ? $primaryOption['attributes'] : '')); ?> type="button" class="btn <?php echo e($primaryOption['css'] ?? ''); ?>"><?php echo e($primaryOption['text']); ?></button>
                        <?php break; ?>
                <?php endswitch; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <?php endif; ?>
            </h1>
          <?php endif; ?>
          
          <!-- .btn-toolbar -->
          <div id="dt-buttons" class="btn-toolbar">
            <?php if(isset($titleButtons)): ?>
            <?php $__currentLoopData = $titleButtons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $titleButton): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(empty($titleButton)): ?>
                <?php continue; ?>;
            <?php endif; ?>
                <?php switch($titleButton['type']):
                    case ('link'): ?>
                        <a <?php echo (isset($titleButton['confirm'])?'onclick="return confirm(\'Sind Sie sicher?\')"':''); ?> href="<?php echo e($titleButton['href']); ?>"><button type="button" class="btn <?php echo e($titleButton['css'] ?? ''); ?>"><?php echo e($titleButton['text']); ?></button></a>
                        <?php break; ?>
                    <?php default: ?>
                        <button id="<?php echo e($titleButton['id']); ?>" <?php echo (isset($titleButton['confirm'])?'onclick="return confirm(\'Sind Sie sicher?\')"':''); ?> <?php echo e((isset($titleButton['attributes']) ? $titleButton['attributes'] : '')); ?> type="button" class="btn <?php echo e($titleButton['css'] ?? ''); ?>"><?php echo e($titleButton['text']); ?></button>
                        <?php break; ?>
                <?php endswitch; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
          </div>
          <!-- /.btn-toolbar -->
        </div>
        <?php if(isset($titleBadge)): ?>
        <span class="badge badge-<?php echo e($titleBadge['type']); ?>"><strong><?php echo e($titleBadge['text']); ?></strong></span>
        <?php endif; ?>
        <?php if(isset($titleBadges)): ?>
          <p>
            <?php $__currentLoopData = $titleBadges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tBadge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if($tBadge['text'] != ''): ?>
              <span class="badge badge-<?php echo e($tBadge['type']); ?>"><strong><?php echo e($tBadge['text']); ?></strong></span>  
              <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </p>
        <?php endif; ?>
        <!-- /title and toolbar -->
        <?php if(session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <?php echo e(session()->get('success')); ?>

        </div>
        <?php endif; ?>
        <?php if(session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <?php echo e(session()->get('error')); ?>

        </div>
        <?php endif; ?>
        <?php if(session()->has('warning')): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <?php echo e(session()->get('warning')); ?>

        </div>
        <?php endif; ?>
      </header>

      <!-- /.page-title-bar --><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/components/header.blade.php ENDPATH**/ ?>