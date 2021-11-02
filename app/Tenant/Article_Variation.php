<?php

namespace App\Tenant;

use App\Http\Controllers\Tenant\ProviderController;
use App\Http\Controllers\Tenant\Providers\VSShop\VSShopController;
use Illuminate\Database\Eloquent\Model;
use App\Manager\Content\ContentManager;

class Article_Variation extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['fk_article_id','vstcl_identifier', 'active', 'name', 'description', 'stock', 'extra_ean', 'min_stock', 'type', 'fk_attributeset_id','ean'];

    protected $hidden = ['created_at', 'updated_at'];

    public static function boot() {
        parent::boot();

        try{
            $content_manager=new ContentManager();

            self::created(function($article_variation)use($content_manager) {
                $content_manager->registrateOperation($article_variation,'insert','scheduled');
            });

            self::updated(function($article_variation)use($content_manager) {
                $content_manager->registrateOperation($article_variation,'update','scheduled');
            });

            self::deleting(function($article_variation)use($content_manager) {
                $content_manager->registrateOperation($article_variation,'delete','scheduled');
            });
        }
        catch(\Exception $e){
            
        }
    }
    /**
     * @author Tanju Özsoy
     * 16.01.2012
     * Wir erstellen jetzt einen Getter für ein Attribut, das es nicht gibt.
     *
     */
    protected $appends=['vsshop_color',
                        'vsshop_size',
                        'vsshop_length',
                        'vsshop_length2',
                    ];
    public function getVsshopColorAttribute(){
        $result=
        $this->attributes()->where('name','=','fee-color')->first();

        $value='';
        if($result){
            $value=$result->value;
        }

        return $value;
    }
    public function getVsshopSizeAttribute(){
        $result=
        $this->attributes()->where('name','=','fee-size')->first();

        $value='';
        if($result){
            $value=$result->value;
        }

        return $value;
    }
    public function getVsshopLengthAttribute(){
        $result=
        $this->attributes()->where('name','=','fee-formLaenge')->first();

        $value='';
        if($result){
            $value=$result->value;
        }

        return $value;
    }
    public function getVsshopLength2Attribute(){
        $result=
        $this->attributes()->where('name','=','fee-sizeLaenge')->first();

        $value='';
        if($result){
            $value=$result->value;
        }

        return $value;
    }

    public function article() {
        return $this->belongsTo(Article::class, 'fk_article_id');
    }

    public function attributes() {
        return $this->hasMany(Article_Variation_Attribute::class, 'fk_article_variation_id');
    }

    public function eigenschaften() {
        return $this->hasMany(Article_Eigenschaften_Articles::class, 'fk_variation_id');
    }

    public function prices() {
        return $this->hasMany(Article_Variation_Price::class, 'fk_article_variation_id');
    }
    public function getStandardPrice() { $price = $this->prices()->where('name', '=', 'standard')->first(); return ($price) ? $price->value : null ; }
    public function getDiscountPrice() { $price = $this->prices()->where('name', '=', 'discount')->first(); return ($price) ? $price->value : null ; }
    public function getWebStandardPrice() { $price = $this->prices()->where('name', '=', 'web_standard')->first(); return ($price) ? $price->value : null ; }
    public function getWebDiscountPrice() { $price = $this->prices()->where('name', '=', 'web_discount')->first(); return ($price) ? $price->value : null ; }

    public function images() {
        return $this->hasMany(Article_Variation_Image::class, 'fk_article_variation_id');
    }

    public function branches() {
        return $this->hasMany(BranchArticle_Variation::class, 'fk_article_variation_id');
    }

    public function attribute_set() {
        return $this->belongsTo(Attribute_Set::class, 'fk_attributeset_id');
    }

    public function getAttrByName($name) {
        return $this->attributes()->where('name', '=', $name)->first() ? $this->attributes()->where('name', '=', $name)->first()->value : '';
    }

    public function getStock() {
        return $this->branches()->sum('stock');
    }

    public function getFirstBranchWithStock() {
        return $this->branches()->where('stock', '>', '0')->orderBy('stock', 'desc')->first();
    }

    public function getStockByBranchIds($branchIds) {
        return $this->branches()->whereIn('fk_branch_id', $branchIds)->sum('stock');
    }



    public function updateOrCreateAttribute($name, $value, $group_id = null){
        return Article_Variation_Attribute::updateOrCreate(
            [
                'fk_article_variation_id' => $this->id,
                'name' => $name
            ],
            [
                'value' =>  $value."",
                'fk_attributegroup_id' => $group_id
            ]
        );
    }

    public function getAttributeValueByKey($key) {
        $attribute = $this->attributes()->where('name','=',$key)->first();
        return (($attribute) ? $attribute->value : '');
    }

    public function updateOrCreatePrice($name, $value) {
        $count=Article_Variation_Price::where('fk_article_variation_id' ,'=', $this->id)
                        ->where('name' ,'=', $name)
                        ->count('*');
        $article_variation_price=
        Article_Variation_Price::updateOrCreate(
            [
                'fk_article_variation_id' => $this->id,
                'name' => $name
            ],
            [
                'value' => $value,
                'batch_nr' => date("YmdHis")
            ]
        );
        if($count){
            VSShopController::update_article_variation_price_job($article_variation_price);
        }
        else{
            VSShopController::create_article_variation_price_job($article_variation_price);
        }
        return $article_variation_price;
    }

    public function updateOrCreateStockInBranch(Branch $branch, $stock, $batch_nr = null) {
        $stockUpdate = BranchArticle_Variation::where('fk_branch_id','=',$branch->id)->where('fk_article_variation_id','=',$this->id)->first();
        if(!$stockUpdate) {
            $branch_article_variation=
            BranchArticle_Variation::updateOrCreate([
                'fk_branch_id' => $branch->id,
                'fk_article_variation_id' => $this->id,
                'stock' => $stock,
                'batch_nr' => $batch_nr
            ]);
            VSShopController::create_stock_job($branch_article_variation);
        }
        else {
            if($stockUpdate->stock != $stock) {
                $updates = ['stock' => $stock];
                if($stockUpdate->batch_nr == null) {
                    $updates['batch_nr'] = $batch_nr;
                }
                $stockUpdate->update($updates);
                VSShopController::update_stock_job($stockUpdate);
            }
        }

        return $stockUpdate;
    }

    public function getEan() {
        if($this->extra_ean != null && $this->extra_ean != '') {
            return $this->extra_ean;
        }
        return str_replace('vstcl-','',$this->vstcl_identifier);
    }

    public function getSizeText() {
        $sizeText = $this->attributes()->where('name', '=', 'fee-size')->first();
        return ($sizeText) ? $sizeText->value : '';
    }

    public function getLengthText() {
        $lengthText = $this->attributes()->where('name', '=', 'fee-length')->first();
        return ($lengthText) ? $lengthText->value : '';
    }

    public function getColorText() {
        $colorText = $this->attributes()->where('name', '=', 'fee-info1')->first();
        if(!$colorText || $colorText->value == '') {
            $colorText = $this->attributes()->where('name', '=', 'fee-color')->first();
        }
        return ($colorText) ? $colorText->value : '';
    }

    public function getOriginalImg() {
        return $this->images()->whereHas('attributes', function($query) {
            $query->where('name','=', 'imgType')->where('value','=', 'original');
        })->get();
    }

    public function getThumbnailSmallImg() {
        return $this->images()->whereHas('attributes', function($query) {
            $query->where('name','=', 'imgType')->where('value','=', '200');
        })->get();
    }

    public function getThumbnailImg() {
        return $this->images()->whereHas('attributes', function($query) {
            $query->where('name','=', 'imgType')->where('value','=', '512');
        })->get();
    }

    public function getThumbnailBigImg() {
        return $this->images()->whereHas('attributes', function($query) {
            $query->where('name','=', 'imgType')->where('value','=', '1024');
        })->get();
    }

    public function isActiveForShop() {
        $isActive = false;

        $inStock = ($this->getStock() > 0);

        if($inStock) {
            $isActive = true;
        }

        return $isActive;
    }

    public function sparesets_spare_article() {
        return $this->hasMany(Sparesets_SpareArticles::class, 'fk_art_var_id');
    }
    public function sparesets_article() {
        return $this->hasMany(Sparesets_Articles::class, 'fk_art_var_id');
    }

    public function equipmentsets_equipment_article() {
        return $this->hasMany(Equipmentsets_EquipmentArticles::class, 'fk_art_var_id');
    }
    public function equipmentsets_article() {
        return $this->hasMany(Equipmentsets_Articles::class, 'fk_art_var_id');
    }

}
