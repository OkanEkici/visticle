<!-- .dropdown -->
<div class="dropdown">
    <button class="btn btn-secondary" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <span>Diesen Monat</span>
      <i class="fa fa-fw fa-caret-down"></i>
    </button>
    <!-- .dropdown-menu -->
    <div class="dropdown-menu dropdown-menu-right dropdown-menu-md stop-propagation">
      <div class="dropdown-arrow"></div>
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpToday" name="dpFilter" value="0">
        <label class="custom-control-label d-flex justify-content-between" for="dpToday">
          <span>Heute</span>
          <span class="text-muted">Mar 27</span>
        </label>
      </div>
      <!-- /.custom-control -->
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpYesterday" name="dpFilter" value="1">
        <label class="custom-control-label d-flex justify-content-between" for="dpYesterday">
          <span>Gestern</span>
          <span class="text-muted">Mar 26</span>
        </label>
      </div>
      <!-- /.custom-control -->
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpWeek" name="dpFilter" value="2">
        <label class="custom-control-label d-flex justify-content-between" for="dpWeek">
          <span>Diese Woche</span>
          <span class="text-muted">Mar 21-27</span>
        </label>
      </div>
      <!-- /.custom-control -->
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpMonth" name="dpFilter" value="3" checked>
        <label class="custom-control-label d-flex justify-content-between" for="dpMonth">
          <span>Diesen Monat</span>
          <span class="text-muted">Mar 1-31</span>
        </label>
      </div>
      <!-- /.custom-control -->
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpYear" name="dpFilter" value="4">
        <label class="custom-control-label d-flex justify-content-between" for="dpYear">
          <span>Dieses Jahr</span>
          <span class="text-muted">2020</span>
        </label>
      </div>
      <!-- /.custom-control -->
      <!-- .custom-control -->
      <div class="custom-control custom-radio">
        <input type="radio" class="custom-control-input" id="dpCustom" name="dpFilter" value="5">
        <label class="custom-control-label" for="dpCustom">Custom</label>
        <div class="custom-control-hint my-1">
          <!-- datepicker:range -->
          <input type="text" class="form-control" data-toggle="flatpickr" data-mode="range" data-date-format="Y-m-d">
          <!-- /datepicker:range -->
        </div>
      </div>
      <!-- /.custom-control -->
    </div>
    <!-- /.dropdown-menu -->
  </div>
  <!-- /.dropdown -->
  <?php /**PATH C:\xampp\htdocs\visticle\resources\views/tenant/components/datepicker.blade.php ENDPATH**/ ?>