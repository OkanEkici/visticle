    <!-- .page-section -->
    <div class="page-section">
      <!-- .card -->
      <div class="card card-fluid">

        <?php if(isset($tabs)): ?>
        <!-- .card-header -->
        <div class="card-header">
            <!-- .nav-tabs -->
            <ul class="nav nav-tabs card-header-tabs">
                <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="nav-item">
                <a class="nav-link <?php echo e($tab['cssClasses'] ?? ''); ?>" data-toggle="tab" href="<?php echo e($tab['href'] ?? '#'); ?>"><?php echo e($tab['name']); ?></a>
                  </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul><!-- /.nav-tabs -->
          </div>
          <!-- /.card-header -->
        <?php else: ?>
            <?php if(isset($title) && !isset($noTableTitle)): ?>
            <!-- .card-header -->
            <div class="card-header"><?php echo $title; ?></div>
            <!-- /.card-header -->  
            <?php endif; ?>
        <?php endif; ?>

        <!-- .card-body -->
        <div class="card-body">
        <?php if(isset($search)): ?>
          <!-- .form-group -->
          <div class="form-group">
            <!-- .input-group -->
            <div class="input-group input-group-alt">

              <!-- .input-group -->
              <div class="input-group has-clearable">
                <button id="clear-search" type="button" class="close" aria-label="Close">
                  <span aria-hidden="true"><i class="fa fa-times-circle"></i></span>
                </button>
                <div class="input-group-prepend">
                  <span class="input-group-text"><span class="oi oi-magnifying-glass"></span></span>
                </div>
                <input id="table-search" type="text" class="form-control" placeholder="<?php echo e($search['placeholder']); ?>">
              </div>
              <!-- /.input-group -->
              <!-- .input-group-append -->
              <div class="input-group-append">
                <button class="btn btn-secondary" data-toggle="modal" data-target="#modalFilterColumns">Filter öffnen</button>
              </div>
              <!-- /.input-group-append -->
            </div>
            <!-- /.input-group -->
          </div>
          <!-- /.form-group -->
        <?php endif; ?> 
          <!-- .table -->
          <table id="<?php echo e($tableId ?? 'myTable'); ?>" class="table">
            <!-- thead -->
            <thead>
              <tr>
                <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(!isset($easyTable) && $loop->first): ?> <?php continue; ?> <?php endif; ?>
                    <?php if($column == 'Reduzierter Web-Preis'): ?>
                      <th><input class="form-control master_reduced_web_price" style="min-width: 70px;" type="number" min="0" step="0.01" value="">
                      <div class="border border-1 mt-2  set_discount_price_btn btn">setzen</div>
                    </th>
                    <?php elseif($column == 'Web-Preis'): ?>
                    <th><input class="form-control master_web_price" style="min-width: 70px;"  type="number" min="0" step="0.01" value="">
                    <div class="border border-1 mt-2 set_standard_price_btn btn">setzen</div></th>
                    <?php else: ?>
                        <th></th>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if(!isset($easyTable)): ?>
                <th style="width:100px; min-width:100px;">&nbsp;</th>
                <?php endif; ?>  
              </tr>
              <tr>
                <?php if(!isset($easyTable)): ?>
                <th colspan="2" style="min-width: <?php echo e($firstColumnWidth ?? '320'); ?>px;">
                  <div class="thead-dd dropdown">
                    <span class="custom-control custom-control-nolabel custom-checkbox">
                      <input type="checkbox" class="custom-control-input" id="check-handle">
                      <label class="custom-control-label" for="check-handle"></label>
                    </span>
                    <div class="thead-btn" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      <span class="fa fa-caret-down"></span>
                    </div>
                    <div class="dropdown-menu">
                      <div class="dropdown-arrow"></div>
                      <a class="dropdown-item" href="#">Alle auswählen</a>
                      <a class="dropdown-item" href="#">Alle abwählen</a>
                      <?php if(isset($bulkOptions)): ?>
                      <div class="dropdown-divider"></div>
                      <?php $__currentLoopData = $bulkOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bulkOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a class="dropdown-item" id="<?php echo e($bulkOption['id'] ?? ''); ?>" <?php echo (isset($bulkOption['confirm'])?'onclick="return confirm(\'Sind Sie sicher?\')"':''); ?> ><?php echo e($bulkOption['text'] ?? ''); ?></a>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                     <?php endif; ?>
                  </div>
                </th>
               <?php endif; ?>
                <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(!isset($easyTable) && $loop->first): ?> <?php continue; ?> <?php endif; ?>
                    <?php if(is_array($column)): ?>
                        <th><?php echo e($column['name']); ?></th>
                    <?php elseif($column == 'Reduzierter Preis aus Wawi'): ?>
                      <th>red. Preis aus Wawi</th>
                    <?php else: ?>
                    <th><?php echo e($column); ?></th>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if(!isset($easyTable)): ?>
                <th style="width:100px; min-width:100px;">&nbsp;</th>
                <?php endif; ?>  
              </tr>
              
            </thead>
            <!-- /thead -->
            <!-- tbody -->
            <tbody>
              <!-- create empty row to passing html validator -->
              <tr>
                <?php if(!isset($easyTable)): ?>
                <td></td>
                <?php endif; ?>
                  <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <td></td>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  <?php if(!isset($easyTable)): ?>
                  <td></td>
                  <?php endif; ?>
              </tr>
              <?php if(isset($content)): ?>
              <?php $__currentLoopData = $content; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <?php if(!isset($easyTable)): ?>
                <td></td>
                <?php endif; ?>
                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <td><?php echo $column; ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <?php endif; ?>
            </tbody>
            <!-- /tbody -->
          </table>
          <!-- /.table -->
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.page-section -->

              <!-- #modalFilterColumns -->
              <div class="modal fade" id="modalFilterColumns" tabindex="-1" role="dialog" aria-labelledby="modalFilterColumnsLabel" aria-hidden="true">
                <!-- .modal-dialog -->
                <div class="modal-dialog modal-dialog-scrollable" role="document">
                  <!-- .modal-content -->
                  <div class="modal-content">
                    <!-- .modal-header -->
                    <div class="modal-header">
                      <h5 class="modal-title" id="modalFilterColumnsLabel">Filter</h5>
                    </div>
                    <!-- /.modal-header -->
                    <!-- .modal-body -->
                    <div class="modal-body">
                      <!-- #filter-columns -->
                      <div id="filter-columns">

    
                        <!-- .form-row -->
                        <div class="form-group form-row filter-row">
                          <!-- form column -->
                          <div class="col">
                            <select class="custom-select filter-control filter-column">
                              <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                
                                <?php if(is_array($column)): ?>
                                    <option value="<?php echo e($key + 1); ?>"><?php echo e($column['name']); ?></option>
                                <?php else: ?>
                                  <option value="<?php echo e($key + 1); ?>"><?php echo e($column); ?></option>
                                <?php endif; ?>
                                
                              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                          </div>
                          <!-- /form column -->
                          <!-- form column -->
                          <div class="col">
                            <select class="custom-select filter-control filter-operator">
                              <option value="contain">Beinhaltet</option>
                              <option value="notcontain">Beinhaltet nicht</option>
                              <option value="equal">Ist gleich</option>
                              <option value="notequal">Ist nicht gleich</option>
                              <option value="beginwith">Beginnt mit</option>
                              <option value="endwith">Ended mit</option>
                              <option value="greaterthan">Größer als</option>
                              <option value="lessthan">Weniger als</option>
                            </select>
                          </div>
                          <!-- /form column -->
                          <!-- form column -->
                          <div class="col">
                            <div class="input-group input-group-alt">
                              <input type="text" class="form-control filter-control filter-value rounded mr-2">
                              <div class="input-group-append">
                                <button class="close remove-filter-row">&times;</button>
                              </div>
                            </div>
                          </div>
                          <!-- /form column -->
                        </div>
                        <!-- /.form-row -->
                      </div>
                      <!-- #filter-columns -->
    
                      <!-- .btn -->
                      <button id="add-filter-row" class="btn btn-reset my-2">
                        <i class="fa fa-plus mr-1"></i> Filter hinzufügen
                      </button>
                      <!-- /.btn -->
                    </div>
                    <!-- /.modal-body -->
                    <!-- .modal-footer -->
                    <div class="modal-footer justify-content-start">
                      <button type="button" class="btn btn-primary" data-dismiss="modal">Filter anwenden</button>
                      <button type="button" class="btn btn-light" id="clear-filter">Abbrechen</button>
                    </div>
                    <!-- /.modal-footer -->
                  </div>
                  <!-- /.modal-content -->
                </div>
                <!-- /.modal-dialog -->
              </div>
              <!-- /#modalFilterColumns --><?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/components/datatable.blade.php ENDPATH**/ ?>