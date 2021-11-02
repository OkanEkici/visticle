<?php
namespace App\Services;

use App\Tenant;
use Storage;

class TenantManager {
    /*
     * @var null|App\Tenant
     */
     private $tenant;
   
    public function setTenant(?Tenant $tenant) {
        $this->tenant = $tenant;
        return $this;
    }
    
    public function getTenant(): ?Tenant {
        return $this->tenant;
    }
    
    public function loadTenant(string $identifier): bool {
        $tenant = Tenant::query()->where('subdomain', '=', $identifier)->first();

        if ($tenant) {
            $this->setTenant($tenant);
            return true;
        }
        
        return false;
    }

    public function createResourceFolder() {
        if(!$this->tenant) {
            return false;
        }
        Storage::disk('customers')->makeDirectory($this->tenant->subdomain);
        Storage::disk('customers')->makeDirectory($this->tenant->subdomain.'/img');
        Storage::disk('public')->makeDirectory($this->tenant->subdomain);
        Storage::disk('public')->makeDirectory($this->tenant->subdomain.'/img');
        Storage::disk('customers')->makeDirectory($this->tenant->subdomain.'/img/products');
        if ($this->tenant->is_fee_customer == true) {
            Storage::disk('customers')->makeDirectory($this->tenant->subdomain.'/feecsv');
            Storage::disk('customers')->makeDirectory($this->tenant->subdomain.'/feecsv_backup');
        }
    }
 }