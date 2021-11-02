

<?php $__env->startSection('content'); ?>
<main class="auth">
        <header id="auth-header" class="auth-header">
          <h1>
          <img src="<?php echo e(asset("assets/img/logo_cutout.PNG")); ?>">
          </h1>
          </p>
        </header><!-- form -->
        <form class="auth-form" method="POST" action="<?php echo e(route('user.authenticate')); ?>">
          <?php echo csrf_field(); ?>
          <!-- .form-group -->
          <div class="form-group">
            <div class="form-label-group">
              <input type="email" id="email" name="email" required class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" placeholder="<?php echo e(__('E-Mail Address')); ?>" autocomplete="email" autofocus=""> <label for="inputUser"><?php echo e(__('E-Mail Address')); ?></label>
                <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <span class="invalid-feedback" role="alert">
                        <strong><?php echo e($message); ?></strong>
                    </span>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div><!-- /.form-group -->
          <!-- .form-group -->
          <div class="form-group">
            <div class="form-label-group">
              <input type="password" id="password" name="password" required class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" placeholder="Passwort"  autocomplete="current-password"> <label for="inputPassword"><?php echo e(__('Password')); ?></label>
                <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <span class="invalid-feedback" role="alert">
                        <strong><?php echo e($message); ?></strong>
                    </span>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
          </div><!-- /.form-group -->
          <!-- .form-group -->
          <div class="form-group">
            <button class="btn btn-lg btn-primary btn-block" type="submit"><?php echo e(__('Login')); ?></button>
          </div><!-- /.form-group -->
          <!-- .form-group -->
          <!--
          <div class="form-group text-center">
            <div class="custom-control custom-control-inline custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="remember" name="remember"  <?php echo e(old('remember') ? 'checked' : ''); ?>> <label class="custom-control-label" for="remember-me"><?php echo e(__('Remember Me')); ?></label>
            </div>
          </div>--><!-- /.form-group -->
          <!-- recovery links -->
          <div class="text-center pt-3">
            <?php if(Route::has('password.request')): ?>
            <a class="btn btn-link" href="<?php echo e(route('password.request')); ?>">
                <?php echo e(__('Forgot Your Password?')); ?>

            </a>
        <?php endif; ?>
          </div><!-- /recovery links -->
        </form><!-- /.auth-form -->
        <!-- copyright -->
        <footer class="auth-footer" style="color: #fff; text-align: center;"> © 2020 - VISC Media UG <br><a href="#">Datenschutzerklärung</a> und <a href="#">Nutzungsbedingungen</a>
        </footer>
      </main><!-- /.auth -->
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layout.mainlayout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\visticle\resources\views/auth/login.blade.php ENDPATH**/ ?>