<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::domain('{identifier}.'.config('app.url'))->middleware(['tenantident'])->group(function (Router $router) {

    //Auth
    Route::get('/login', 'Auth\Tenant\LoginController@index')->name('tenant.login');
    Route::get('/logout', 'Auth\Tenant\LoginController@logout')->name('tenant.logout');
    /*Route::get('/logout', 'Auth\Tenant\LoginController@logout', function () {
        return abort(404);
    })->name('tenant.logout');*/
    Route::post('/authenticate', 'Auth\Tenant\LoginController@authenticate')->name('tenant.auth');

    //Dashboard
    Route::get('/', 'Tenant\TenantUserController@dashboard')->name('tenant.dashboard')->middleware('auth:tenant');

    //Articles
    Route::post('/articles/categoryUpdateAjax', 'Tenant\ArticleController@updateCategoryAjax')->name('tenant.articles.category.update.ajax')->middleware('auth:tenant');
    Route::post('/articles/update/{id}/{part}', 'Tenant\ArticleController@update')->name('tenant.articles.update')->middleware('auth:tenant');
    Route::get('/articles/delete/{id}', 'Tenant\ArticleController@destroy')->name('tenant.articles.delete')->middleware('auth:tenant');
    Route::get('/articles/new', 'Tenant\ArticleController@create')->name('tenant.articles.create')->middleware('auth:tenant');
    Route::post('/articles/store', 'Tenant\ArticleController@store')->name('tenant.articles.store')->middleware('auth:tenant');
    Route::post('/articles', 'Tenant\ArticleController@index')->name('tenant.articles.index.ajax')->middleware('auth:tenant');
    Route::get('/articles','Tenant\ArticleController@index')->name('tenant.articles.index')->middleware('auth:tenant');

    Route::post('/articles/hauptartikel', 'Tenant\ArticleController@index_hauptartikel')->name('tenant.articles.index_hauptartikel.ajax')->middleware('auth:tenant');
    Route::get('/articles/hauptartikel','Tenant\ArticleController@index_hauptartikel')->name('tenant.articles.index_hauptartikel')->middleware('auth:tenant');

    Route::post('/articles/ersatzteile', 'Tenant\ArticleController@index_ersatzteile')->name('tenant.articles.index_ersatzteile.ajax')->middleware('auth:tenant');
    Route::get('/articles/ersatzteile','Tenant\ArticleController@index_ersatzteile')->name('tenant.articles.index_ersatzteile')->middleware('auth:tenant');

    Route::post('/articles/zubehoerartikel', 'Tenant\ArticleController@index_zubehoerartikel')->name('tenant.articles.index_zubehoerartikel.ajax')->middleware('auth:tenant');
    Route::get('/articles/zubehoerartikel','Tenant\ArticleController@index_zubehoerartikel')->name('tenant.articles.index_zubehoerartikel')->middleware('auth:tenant');

    Route::get('/articles/show/{id}/{part}', 'Tenant\ArticleController@show')->name('tenant.articles.show')->middleware('auth:tenant');

    Route::get('/articles/show/{id}/{part}/update/{set_id}', 'Tenant\ArticleController@update_sets')->name('tenant.articles.update.sets')->middleware('auth:tenant');
    Route::get('/articles/show/{id}/{part}/delete/{set_id}', 'Tenant\ArticleController@delete_sets')->name('tenant.articles.delete.sets')->middleware('auth:tenant');

    Route::get('/articles/edit/{id}', 'Tenant\ArticleController@edit')->name('tenant.articles.edit')->middleware('auth:tenant');
    //Articles - Variations
    Route::get('/articles/{id}/variations', 'Tenant\ArticleController@loadVariations')->name('tenant.articles.variations.ajax')->middleware('auth:tenant');
    Route::get('/articles/{id}/variation/{vid}/delete', 'Tenant\ArticleController@deleteVariation')->name('tenant.articles.variation.delete')->middleware('auth:tenant');
    //Article - Categories
    Route::get('/articles/{id}/categories/{part}', 'Tenant\ArticleController@loadCategories')->name('tenant.articles.categories.ajax')->middleware('auth:tenant');
    //Article - Images
    Route::get('/articles/{id}/images/{imgId}/delete', 'Tenant\ArticleController@destroyImage')->name('tenant.articles.images.delete')->middleware('auth:tenant');
    //Article - Ersatzteile
    Route::post('/sparesets', 'Tenant\SparesetController@index')->name('tenant.articles.sparesets.index.ajax')->middleware('auth:tenant');
    Route::get('/sparesets','Tenant\SparesetController@index')->name('tenant.articles.sparesets.index')->middleware('auth:tenant');
    Route::get('/sparesets/new', 'Tenant\SparesetController@create')->name('tenant.articles.sparesets.create')->middleware('auth:tenant');
    Route::post('/sparesets/store', 'Tenant\SparesetController@store')->name('tenant.articles.sparesets.store')->middleware('auth:tenant');
    Route::get('/sparesets/show/{id}/{part}', 'Tenant\SparesetController@show')->name('tenant.articles.sparesets.show')->middleware('auth:tenant');
    Route::post('/sparesets/update/{id}/{part}', 'Tenant\SparesetController@update')->name('tenant.articles.sparesets.update')->middleware('auth:tenant');
    Route::get('/sparesets/delete/{id}', 'Tenant\SparesetController@delete')->name('tenant.articles.sparesets.delete')->middleware('auth:tenant');
    Route::get('/sparesets/show/{id}/{part}/update/{article_id}', 'Tenant\SparesetController@update')->name('tenant.articles.sparesets.update.part')->middleware('auth:tenant');
    Route::get('/sparesets/show/{id}/{part}/delete/{article_id}', 'Tenant\SparesetController@delete')->name('tenant.articles.sparesets.delete.part')->middleware('auth:tenant');
    //Article - Zubehör
    Route::post('/equipmentsets', 'Tenant\EquipmentsetController@index')->name('tenant.articles.equipmentsets.index.ajax')->middleware('auth:tenant');
    Route::get('/equipmentsets','Tenant\EquipmentsetController@index')->name('tenant.articles.equipmentsets.index')->middleware('auth:tenant');
    Route::get('/equipmentsets/new', 'Tenant\EquipmentsetController@create')->name('tenant.articles.equipmentsets.create')->middleware('auth:tenant');
    Route::post('/equipmentsets/store', 'Tenant\EquipmentsetController@store')->name('tenant.articles.equipmentsets.store')->middleware('auth:tenant');
    Route::get('/equipmentsets/show/{id}/{part}', 'Tenant\EquipmentsetController@show')->name('tenant.articles.equipmentsets.show')->middleware('auth:tenant');
    Route::get('/equipmentsets/delete/{id}', 'Tenant\EquipmentsetController@delete')->name('tenant.articles.equipmentsets.delete')->middleware('auth:tenant');
    Route::post('/equipmentsets/update/{id}/{part}', 'Tenant\EquipmentsetController@update')->name('tenant.articles.equipmentsets.update')->middleware('auth:tenant');
    Route::get('/equipmentsets/show/{id}/{part}/update/{article_id}', 'Tenant\EquipmentsetController@update')->name('tenant.articles.equipmentsets.update.part')->middleware('auth:tenant');
    Route::get('/equipmentsets/show/{id}/{part}/delete/{article_id}', 'Tenant\EquipmentsetController@delete')->name('tenant.articles.equipmentsets.delete.part')->middleware('auth:tenant');
    // Attributverwaltung
    Route::get('/article_attributes','Tenant\ArticleController@index_attribute_groups')->name('tenant.articles.article_attributes.index')->middleware('auth:tenant');
    Route::post('/article_attributes/new', 'Tenant\ArticleController@create_attribute_groups')->name('tenant.articles.article_attributes.create')->middleware('auth:tenant');
    Route::post('/article_attributes/update', 'Tenant\ArticleController@update_attribute_groups')->name('tenant.articles.article_attributes.update')->middleware('auth:tenant');
    Route::post('/article_attributes/delete', 'Tenant\ArticleController@delete_attribute_groups')->name('tenant.articles.article_attributes.delete')->middleware('auth:tenant');
    // Eigenschaftenverwaltung
    Route::get('/article_eigenschaften','Tenant\ArticleEigenschaftenController@index')->name('tenant.articles.article_eigenschaften.index')->middleware('auth:tenant');
    Route::post('/article_eigenschaften/new', 'Tenant\ArticleEigenschaftenController@create')->name('tenant.articles.article_eigenschaften.create')->middleware('auth:tenant');
    Route::post('/article_eigenschaften/update', 'Tenant\ArticleEigenschaftenController@update')->name('tenant.articles.article_eigenschaften.update')->middleware('auth:tenant');
    Route::post('/article_eigenschaften/delete', 'Tenant\ArticleEigenschaftenController@delete')->name('tenant.articles.article_eigenschaften.delete')->middleware('auth:tenant');

    //Supplier
    Route::get('/suppliers', 'Tenant\BrandController@suppliersIndex')->name('tenant.suppliers.index')->middleware('auth:tenant');
    Route::get('/suppliers/brands', 'Tenant\BrandController@index')->name('tenant.brands.index')->middleware('auth:tenant');
    Route::get('/suppliers/brands/create', 'Tenant\BrandController@create')->name('tenant.brands.create')->middleware('auth:tenant');
    Route::get('/suppliers/brands/show/{id}', 'Tenant\BrandController@show')->name('tenant.brands.show')->middleware('auth:tenant');
    Route::get('/suppliers/brands/edit/{id}', 'Tenant\BrandController@edit')->name('tenant.brands.edit')->middleware('auth:tenant');
    Route::post('/suppliers/brands/store', 'Tenant\BrandController@store')->name('tenant.brands.store')->middleware('auth:tenant');
    Route::post('/suppliers/brands/update/{id}', 'Tenant\BrandController@update')->name('tenant.brands.update')->middleware('auth:tenant');
    Route::get('/suppliers/brands/delete/{id}', 'Tenant\BrandController@destroy')->name('tenant.brands.delete')->middleware('auth:tenant');
    Route::post('/suppliers/brandUpdateAjax', 'Tenant\BrandController@updateParentAjax')->name('tenant.brands.parent.ajax')->middleware('auth:tenant');

    //Categories (Visticle Kategorien)
    Route::post('/warengruppen', 'Tenant\CategoryController@indexWaregroups')->name('tenant.categories.index.wareGroups.ajax')->middleware('auth:tenant');
    Route::get('/warengruppen', 'Tenant\CategoryController@indexWaregroups')->name('tenant.categories.index.wareGroups')->middleware('auth:tenant');
    Route::get('/warengruppen-test','Tenant\CategoryController@test')->middleware('auth:tenant');

    //Extra EANs
    Route::get('/extra-ean', 'Tenant\ArticleController@indexExtraEAN')->name('tenant.articles.ean.index')->middleware('auth:tenant');
    Route::post('/extra-ean/store', 'Tenant\ArticleController@saveExtraEAN')->name('tenant.articles.ean.save')->middleware('auth:tenant');
    Route::get('/delete-extra-ean/{varId}', 'Tenant\ArticleController@destroyExtraEAN')->name('tenant.articles.ean.delete')->middleware('auth:tenant');

    //Categories (Warengruppen)
    Route::post('/categories/update/{id}', 'Tenant\CategoryController@update')->name('tenant.categories.update')->middleware('auth:tenant');
    Route::post('/categories', 'Tenant\CategoryController@index')->name('tenant.categories.index.ajax')->middleware('auth:tenant');
    Route::get('/categories', 'Tenant\CategoryController@index')->name('tenant.categories.index')->middleware('auth:tenant');
    Route::get('/categories/create', 'Tenant\CategoryController@create')->name('tenant.categories.create')->middleware('auth:tenant');
    Route::post('/categories/store', 'Tenant\CategoryController@store')->name('tenant.categories.store')->middleware('auth:tenant');
    Route::get('/categories/delete/{id}', 'Tenant\CategoryController@destroy')->name('tenant.categories.delete')->middleware('auth:tenant');
    Route::get('/categories/show/{id}', 'Tenant\CategoryController@show')->name('tenant.categories.show')->middleware('auth:tenant');
    Route::get('/categories/edit/{id}', 'Tenant\CategoryController@edit')->name('tenant.categories.edit')->middleware('auth:tenant');
    Route::post('/categories/updateAjax', 'Tenant\CategoryController@updateAjax')->name('tenant.categories.update.ajax')->middleware('auth:tenant');
    Route::post('/categories/categoryUpdateAjax', 'Tenant\CategoryController@updateParentAjax')->name('tenant.categories.parent.ajax')->middleware('auth:tenant');

    //Orders
    Route::post('/orders/update/{id}', 'Tenant\OrderController@update')->name('tenant.orders.update')->middleware('auth:tenant');
    Route::get('/orders/update/status/{id}/{statusId}', 'Tenant\OrderController@updateStatus')->name('tenant.orders.update.status')->middleware('auth:tenant');
    Route::post('/orders/update/{id}/payment', 'Tenant\OrderController@updatePayment')->name('tenant.orders.update.payment')->middleware('auth:tenant');
    Route::get('/orders/new', 'Tenant\OrderController@create')->name('tenant.orders.create')->middleware('auth:tenant');
    Route::post('/orders', 'Tenant\OrderController@index')->name('tenant.orders.index.ajax')->middleware('auth:tenant');
    Route::get('/orders', 'Tenant\OrderController@index')->name('tenant.modules.orders.index')->middleware('auth:tenant');
    Route::post('/orders/store', 'Tenant\OrderController@store')->name('tenant.orders.store')->middleware('auth:tenant');
    Route::get('/orders/show/{id}', 'Tenant\OrderController@show')->name('tenant.orders.show')->middleware('auth:tenant');
    Route::get('/orders/edit/{id}', 'Tenant\OrderController@edit')->name('tenant.orders.edit')->middleware('auth:tenant');
    Route::get('/orders/{id}/articles', 'Tenant\OrderController@articles')->name('tenant.orders.articles.ajax')->middleware('auth:tenant');
    Route::post('/orders/update/{id}/retour', 'Tenant\OrderController@updateRetour')->name('tenant.orders.update.retour')->middleware('auth:tenant');
    Route::get('/orders/single/{id}/retour/{ean}/{quantity}', 'Tenant\OrderController@singleRetour')->name('tenant.orders.single.retour')->middleware('auth:tenant');
    //Orders Check24 Tracking-ID
    Route::post('/orders/{order}/trackingid/add','Tenant\OrderController@addTrackingID')->name('tenant.orders.trackingid.add')->middleware('auth:tenant');
    //Orders PDF
    Route::get('/orders/{id}/documents/create_all', 'Tenant\OrderController@createAllDocuments')->name('tenant.orders.documents.create_all')->middleware('auth:tenant');
    Route::post('/orders/{id}/documents/create/{docType}', 'Tenant\OrderController@createDocument')->name('tenant.orders.documents.create')->middleware('auth:tenant');
    Route::get('/orders/{id}/documents', 'Tenant\OrderController@getDocuments')->name('tenant.orders.documents.ajax')->middleware('auth:tenant');
    Route::get('/orders/{id}/pdf/{docType}/{file}', 'Tenant\OrderController@downloadDocument')->name('tenant.orders.pdf.downloadDocument.file')->middleware('auth:tenant');
	Route::get('/orders/{id}/pdf/{docType}', 'Tenant\OrderController@downloadDocument')->name('tenant.orders.pdf.downloadDocument')->middleware('auth:tenant');
    //Orders/Commissions
    Route::get('/commissions', 'Tenant\CommissionController@index')->name('tenant.commissions.index')->middleware('auth:tenant');
    Route::get('/commissions/create', 'Tenant\CommissionController@create')->name('tenant.commissions.create')->middleware('auth:tenant');
    Route::post('/commissions/update/{id?}', 'Tenant\CommissionController@update')->name('tenant.commissions.update')->middleware('auth:tenant');
    Route::get('/commissions/loadOrders', 'Tenant\CommissionController@loadOrders')->name('tenant.commissions.create.orders.ajax')->middleware('auth:tenant');
    Route::get('/commissions/edit/{id}', 'Tenant\CommissionController@edit')->name('tenant.commissions.edit')->middleware('auth:tenant');
    Route::get('/commissions/{id}/order/{orderId}', 'Tenant\CommissionController@showOrder')->name('tenant.commissions.show.order')->middleware('auth:tenant');
    Route::get('/commissions/{id}/order/{orderId}/load', 'Tenant\CommissionController@loadArticlesForOrder')->name('tenant.modules.order.commission.ajax.order')->middleware('auth:tenant');
    Route::post('/commissions/{id}/order/{orderId}/update', 'Tenant\CommissionController@updateOrder')->name('tenant.modules.order.commission.order.update')->middleware('auth:tenant');
    Route::get('/commissions/{id}/order/{orderId}/complete/{next?}', 'Tenant\CommissionController@completeOrder')->name('tenant.modules.order.commission.order.complete')->middleware('auth:tenant');
    Route::get('/commissions/{id}/order/{orderId}/downloadPacking', 'Tenant\CommissionController@downloadPacking')->name('tenant.modules.order.commission.ajax.order.downloadPacking')->middleware('auth:tenant');
    Route::get('/commissions/{id}/pdf', 'Tenant\CommissionController@downloadPDF')->name('tenant.modules.order.commission.pdf')->middleware('auth:tenant');
    Route::get('/commissions/{id}/loadPackscheine', 'Tenant\CommissionController@loadPackscheine')->name('tenant.modules.order.commission.ajax.packscheine')->middleware('auth:tenant');
    Route::get('/commissions/{id}/download', 'Tenant\CommissionController@downloadPDF')->name('tenant.modules.order.commission.ajax.download')->middleware('auth:tenant');
    //Orders/Delivery Notes
    Route::get('/delivery_notes', 'Tenant\OrderController@indexDeliveryNotes')->name('tenant.orders.delivery_notes.index')->middleware('auth:tenant');
    Route::post('/delivery_notes', 'Tenant\OrderController@indexDeliveryNotes')->name('tenant.orders.delivery_notes.index.ajax')->middleware('auth:tenant');
    //Orders/Order Confirmations
    Route::get('/order_confirmations', 'Tenant\OrderController@indexOrderConfirmations')->name('tenant.orders.order_confirmations.index')->middleware('auth:tenant');
    Route::post('/order_confirmations', 'Tenant\OrderController@indexOrderConfirmations')->name('tenant.orders.order_confirmations.index.ajax')->middleware('auth:tenant');
    //Orders/Invoices
    Route::get('/invoices', 'Tenant\OrderController@indexInvoices')->name('tenant.orders.invoices.index')->middleware('auth:tenant');
    Route::post('/invoices', 'Tenant\OrderController@indexInvoices')->name('tenant.orders.invoices.index.ajax')->middleware('auth:tenant');
    //Orders/Retours
    Route::get('/retours', 'Tenant\OrderController@indexRetours')->name('tenant.orders.retours.index')->middleware('auth:tenant');
    Route::post('/retours', 'Tenant\OrderController@indexRetours')->name('tenant.orders.retours.index.ajax')->middleware('auth:tenant');
    //Orders/Credit Notes
    Route::get('/credit_notes', 'Tenant\OrderController@indexCreditNotes')->name('tenant.orders.credit_notes.index')->middleware('auth:tenant');
    Route::post('/credit_notes', 'Tenant\OrderController@indexCreditNotes')->name('tenant.orders.credit_notes.index.ajax')->middleware('auth:tenant');
    //Orders/OPOS
    Route::get('/opos', 'Tenant\OrderController@indexOpos')->name('tenant.orders.opos.index')->middleware('auth:tenant');
    Route::post('/opos', 'Tenant\OrderController@indexOpos')->name('tenant.orders.opos.index.ajax')->middleware('auth:tenant');
    //Orders/Conflicts
    Route::get('/conflicts', 'Tenant\OrderController@indexConflicts')->name('tenant.orders.conflicts.index')->middleware('auth:tenant');
    Route::post('/conflicts', 'Tenant\OrderController@indexConflicts')->name('tenant.orders.conflicts.index.ajax')->middleware('auth:tenant');
    //Orders/Partial Shipments
    Route::get('/partial_shipments', 'Tenant\OrderController@indexPartialShipments')->name('tenant.orders.partial_shipments.index')->middleware('auth:tenant');
    Route::post('/partial_shipments', 'Tenant\OrderController@indexPartialShipments')->name('tenant.orders.partial_shipments.index.ajax')->middleware('auth:tenant');
    //Orders/Statistics
    Route::get('/statistics', 'Tenant\OrderController@indexStatistics')->name('tenant.orders.statistics.index')->middleware('auth:tenant');
    Route::post('/statistics', 'Tenant\OrderController@indexStatistics')->name('tenant.orders.statistics.index.ajax')->middleware('auth:tenant');

    //Customers
    Route::post('/customers', 'Tenant\CustomerController@index')->name('tenant.customers.index.ajax')->middleware('auth:tenant');
    Route::get('/customers', 'Tenant\CustomerController@index')->name('tenant.modules.customers.index')->middleware('auth:tenant');
    Route::get('/customers/new', 'Tenant\CustomerController@create')->name('tenant.customers.create')->middleware('auth:tenant');
    Route::post('/customers/store', 'Tenant\CustomerController@store')->name('tenant.customers.store')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/{part}', 'Tenant\CustomerController@edit')->name('tenant.customers.edit')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/{part}', 'Tenant\CustomerController@update')->name('tenant.customers.update')->middleware('auth:tenant');
    Route::get('/customers/delete/{id}', 'Tenant\CustomerController@destroy')->name('tenant.customers.delete')->middleware('auth:tenant');

    Route::post('/customers/edit/{id}/article_prices/UpdateAjax', 'Tenant\CustomerController@article_prices_UpdateAjax')->name('tenant.modules.customers.prices.update.ajax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/article_prices/priceRelUpdateAjax', 'Tenant\CustomerController@priceRelUpdateAjax')->name('tenant.modules.customers.prices.update.priceRelUpdateAjax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/article_prices/priceRelValueUpdateAjax', 'Tenant\CustomerController@priceRelValueUpdateAjax')->name('tenant.modules.customers.prices.update.priceRelValueUpdateAjax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/article_prices/StandardPriceUpdateAjax', 'Tenant\CustomerController@StandardPriceUpdateAjax')->name('tenant.modules.customers.prices.update.StandardPriceUpdateAjax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/article_prices/DiscountPriceUpdateAjax', 'Tenant\CustomerController@DiscountPriceUpdateAjax')->name('tenant.modules.customers.prices.update.DiscountPriceUpdateAjax')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/article_prices/delete/{article_id}/UpdateAjax', 'Tenant\CustomerController@deleteCustomerArticlePriceUpdateAjax')->name('tenant.modules.customers.prices.update.deleteCustomerArticlePriceUpdateAjax')->middleware('auth:tenant');

    Route::get('/customers/edit/{id}/category_vouchers', 'Tenant\CustomerController@category_vouchers')->name('tenant.modules.customers.categoryvouchers.index')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/category_vouchers/UpdateAjax', 'Tenant\CustomerController@category_vouchers_UpdateAjax')->name('tenant.modules.customers.categoryvouchers.update.ajax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/category_vouchers/priceRelUpdateAjax', 'Tenant\CustomerController@category_vouchers_priceRelUpdateAjax')->name('tenant.modules.customers.categoryvouchers.update.priceRelUpdateAjax')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/category_vouchers/priceRelValueUpdateAjax', 'Tenant\CustomerController@category_vouchers_priceRelValueUpdateAjax')->name('tenant.modules.customers.categoryvouchers.update.priceRelValueUpdateAjax')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/category_vouchers/delete/{category_id}/UpdateAjax/{price_group_id}', 'Tenant\CustomerController@deleteCustomerCategoryVoucherUpdateAjax')->name('tenant.modules.customers.categoryvouchers.update.deleteCustomerCategoryVoucherUpdateAjax')->middleware('auth:tenant');

    Route::get('/customers/edit/{id}/vouchers', 'Tenant\CustomerController@vouchers')->name('tenant.modules.customers.vouchers.index')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/vouchers/UpdateAjax', 'Tenant\CustomerController@vouchers_UpdateAjax')->name('tenant.modules.customers.vouchers.update.ajax')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/vouchers/delete/{price_group_id}/UpdateAjax', 'Tenant\CustomerController@deleteCustomerVoucherUpdateAjax')->name('tenant.modules.customers.vouchers.update.deleteCustomerVoucherUpdateAjax')->middleware('auth:tenant');

    Route::get('/customers/edit/{id}/ansprechpartner/new', 'Tenant\CustomerController@create_ansprechpartner')->name('tenant.customers.ansprechpartner.create')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/ansprechpartner/store', 'Tenant\CustomerController@store_ansprechpartner')->name('tenant.customers.ansprechpartner.store')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/ansprechpartner/{ansprechpartner_id}', 'Tenant\CustomerController@show_ansprechpartner')->name('tenant.customers.ansprechpartner.show')->middleware('auth:tenant');
    Route::post('/customers/edit/{id}/ansprechpartner/{ansprechpartner_id}/edit', 'Tenant\CustomerController@edit_ansprechpartner')->name('tenant.customers.ansprechpartner.edit')->middleware('auth:tenant');
    Route::get('/customers/edit/{id}/ansprechpartner/{ansprechpartner_id}/delete', 'Tenant\CustomerController@delete_ansprechpartner')->name('tenant.customers.ansprechpartner.delete')->middleware('auth:tenant');

    //Zahlungsbedingungen
    Route::post('/zahlungsbedingungen', 'Tenant\CustomerController@index_zahlungsbedingungen')->name('tenant.modules.customers.index_zahlungsbedingungen.ajax')->middleware('auth:tenant');
    Route::get('/zahlungsbedingungen', 'Tenant\CustomerController@index_zahlungsbedingungen')->name('tenant.modules.customers.index_zahlungsbedingungen')->middleware('auth:tenant');
    Route::post('/zahlungsbedingungen/store', 'Tenant\CustomerController@store_zahlungsbedingungen')->name('tenant.customers.store_zahlungsbedingungen')->middleware('auth:tenant');
    Route::post('/zahlungsbedingungen/edit/{id}', 'Tenant\CustomerController@save_zahlungsbedingungen')->name('tenant.customers.save_zahlungsbedingungen')->middleware('auth:tenant');
    Route::get('/zahlungsbedingungen/delete/{id}', 'Tenant\CustomerController@delete_zahlungsbedingungen')->name('tenant.customers.delete_zahlungsbedingungen')->middleware('auth:tenant');


    Route::get('/customers/edit/{customer_id}/payment_conditions_customers/update/{condition_id}', 'Tenant\CustomerController@update_customer_zahlungsbedingungen')->name('tenant.customers.update.zahlungsbedingungen')->middleware('auth:tenant');
    Route::get('/customers/edit/{customer_id}/payment_conditions_customers/delete/{condition_id}', 'Tenant\CustomerController@delete_customer_zahlungsbedingungen')->name('tenant.customers.delete.zahlungsbedingungen')->middleware('auth:tenant');


    //Price Groups
    Route::post('/pricegroups', 'Tenant\PriceGroupController@index')->name('tenant.pricegroups.index.ajax')->middleware('auth:tenant');
    Route::get('/pricegroups', 'Tenant\PriceGroupController@index')->name('tenant.modules.pricegroups.index')->middleware('auth:tenant');
    Route::get('/pricegroups/new', 'Tenant\PriceGroupController@create')->name('tenant.modules.pricegroups.create')->middleware('auth:tenant');
    Route::post('/pricegroups/store', 'Tenant\PriceGroupController@store')->name('tenant.modules.pricegroups.store')->middleware('auth:tenant');
    Route::get('/pricegroups/edit/{id}', 'Tenant\PriceGroupController@edit')->name('tenant.modules.pricegroups.edit')->middleware('auth:tenant');
    Route::post('/pricegroups/edit/{id}', 'Tenant\PriceGroupController@update')->name('tenant.modules.pricegroups.update')->middleware('auth:tenant');
    Route::get('/pricegroups/delete/{id}', 'Tenant\PriceGroupController@destroy')->name('tenant.modules.pricegroups.delete')->middleware('auth:tenant');
    Route::get('/pricegroups/articles', 'Tenant\PriceGroupController@articles')->name('tenant.modules.pricegroups.articles')->middleware('auth:tenant');
    Route::post('/pricegroups/articles', 'Tenant\PriceGroupController@articles')->name('tenant.modules.pricegroups.articles.ajax')->middleware('auth:tenant');
    Route::post('/pricegroups/articles/pricegroupsUpdateAjax', 'Tenant\PriceGroupController@pricegroups_articles_UpdateAjax')->name('tenant.modules.pricegroups.articles.update.ajax')->middleware('auth:tenant');
    Route::get('/pricegroups/categories', 'Tenant\PriceGroupController@categories')->name('tenant.modules.pricegroups.categories')->middleware('auth:tenant');
    Route::post('/pricegroups/categories', 'Tenant\PriceGroupController@categories')->name('tenant.modules.pricegroups.categories.ajax')->middleware('auth:tenant');
    Route::post('/pricegroups/categories/pricegroupsUpdateAjax', 'Tenant\PriceGroupController@pricegroups_categories_UpdateAjax')->name('tenant.modules.pricegroups.categories.update.ajax')->middleware('auth:tenant');
    Route::get('/pricegroups/customers', 'Tenant\PriceGroupController@customers')->name('tenant.modules.pricegroups.customers')->middleware('auth:tenant');
    Route::post('/pricegroups/customers', 'Tenant\PriceGroupController@customers')->name('tenant.modules.pricegroups.customers.ajax')->middleware('auth:tenant');
    Route::post('/pricegroups/customers/pricegroupsUpdateAjax', 'Tenant\PriceGroupController@pricegroups_customers_UpdateAjax')->name('tenant.modules.pricegroups.customers.update.ajax')->middleware('auth:tenant');

    //Provider
    Route::get('/provider', 'Tenant\ProviderController@index')->name('tenant.provider.index')->middleware('auth:tenant');
    Route::get('/provider/loadArticles/{id}', 'Tenant\ProviderController@loadArticles')->name('tenant.provider.articles.ajax')->middleware('auth:tenant');
    Route::get('/provider/{id}/{part}', 'Tenant\ProviderController@show')->name('tenant.provider.show')->middleware('auth:tenant');
    Route::post('/provider/update/{id}/{part}', 'Tenant\ProviderController@update')->name('tenant.provider.update')->middleware('auth:tenant');
    Route::get('/init_shop/{id}', 'Tenant\ArticleController@sendArticlesToShop')->name('tenant.provider.init_shop')->middleware('auth:tenant');

    //Sortierung
    Route::post('/provider/{id}/shop_sort/UpdateAjax', 'Tenant\ProviderController@ShopSort_UpdateAjax')->name('tenant.provider.shopsort.UpdateAjax')->middleware('auth:tenant');
    Route::get('/provider/{provider_id}/shop_sort/delete/{sai_id}', 'Tenant\ProviderController@ShopSort_deleteUpdateAjax')->name('tenant.provider.shopsort.delete.UpdateAjax')->middleware('auth:tenant');
    Route::get('/provider/{provider_id}/shop_sort/up/{sai_id}', 'Tenant\ProviderController@ShopSort_upUpdateAjax')->name('tenant.provider.shopsort.up.UpdateAjax')->middleware('auth:tenant');
    Route::get('/provider/{provider_id}/shop_sort/down/{sai_id}', 'Tenant\ProviderController@ShopSort_downUpdateAjax')->name('tenant.provider.shopsort.down.UpdateAjax')->middleware('auth:tenant');

    //Settings
    Route::get('/settings/{part}', 'Tenant\SettingController@show')->name('tenant.user.settings')->middleware('auth:tenant');
    Route::post('/settings/update/{part}', 'Tenant\SettingController@update')->name('tenant.user.settings.update')->middleware('auth:tenant');
    Route::post('/settings/partner/{partner}/update/{part}', 'Tenant\SettingController@updatePartner')->name('tenant.user.settings.partner.update')->middleware('auth:tenant');
    Route::get('/settings/partner/{partner}/{part}', 'Tenant\SettingController@showPartner')->name('tenant.user.settings.partner')->middleware('auth:tenant');


    Route::get('/settings/payment_conditions/new', 'Tenant\SettingController@create_payment_condition')->name('tenant.user.settings.create_payment_condition')->middleware('auth:tenant');
    Route::post('/settings/payment_conditions/new', 'Tenant\SettingController@create_payment_condition')->name('tenant.user.settings.create_payment_condition.create')->middleware('auth:tenant');
    Route::get('/settings/payment_conditions/{condition_id}', 'Tenant\SettingController@edit_payment_condition')->name('tenant.user.settings.payment_condition.show')->middleware('auth:tenant');
    Route::post('/settings/payment_conditions/{condition_id}', 'Tenant\SettingController@edit_payment_condition')->name('tenant.user.settings.payment_condition.update')->middleware('auth:tenant');
    Route::get('/settings/payment_conditions/delete/{condition_id}', 'Tenant\SettingController@delete_payment_condition')->name('tenant.user.settings.payment_condition.delete')->middleware('auth:tenant');
    Route::get('/settings/payment_conditions/{condition_id}/customer_conditions', 'Tenant\SettingController@show_payment_customer_conditions')->name('tenant.user.settings.payment_condition.customer_conditions.show')->middleware('auth:tenant');
    Route::get('/settings/payment_conditions/{condition_id}/customer_conditions/update/{customer_id}', 'Tenant\SettingController@update_payment_customer_conditions')->name('tenant.user.settings.payment_condition.customer_conditions.update')->middleware('auth:tenant');
    Route::get('/settings/payment_conditions/{condition_id}/customer_conditions/delete/{customer_id}', 'Tenant\SettingController@delete_payment_customer_conditions')->name('tenant.user.settings.payment_condition.customer_conditions.delete')->middleware('auth:tenant');

    //Legal
    Route::view('/legal/legal-notice', 'tenant.info.legal.legal-notice')->name('tenant.info.legal.legal-notice')->middleware('auth:tenant');
    Route::view('/legal/data-privacy-policy', 'tenant.info.legal.data-privacy-policy')->name('tenant.info.legal.data-privacy-policy')->middleware('auth:tenant');
    Route::view('/legal/disclaimer', 'tenant.info.legal.disclaimer')->name('tenant.info.legal.disclaimer')->middleware('auth:tenant');

    //Storage
    Route::get('image/{filename}', 'Tenant\TenantUserController@displayImage')->name('tenant.displayImage')->middleware('auth:tenant');

    //Downloads
    Route::get('/download/synchrofile/{synchroId}', 'Tenant\SynchroController@downloadFile')->name('tenant.synchro.download')->middleware('auth:tenant');

    //FashionCloud
    Route::post('fashioncloud/multiarticles', 'Tenant\Providers\Fashioncloud\FashionCloudController@syncMultipleArticles')->name('partner.fashioncloud.syncArticles')->middleware('auth:tenant');
    Route::get('fashioncloud/articles/{id}/{redirect?}', 'Tenant\Providers\Fashioncloud\FashionCloudController@syncArticleOnly')->name('partner.fashioncloud.syncArticle')->middleware('auth:tenant');

    //User Related Configs
    Route::post('/user/config/columns/{tableName}', 'Tenant\TenantUserConfigController@updateDatatablesColumnConfig')->name('tenant.user.config.columns')->middleware('auth:tenant');

    //Shopware Test
    Route::get('/shopware/test/{providerId}', 'Tenant\Providers\Shopware\ShopwareProviderController@testAPI')->name('tenant.shopware.test')->middleware('auth:tenant');

});

Route::domain(config('app.url'))->group(function (Router $router) {

    Route::get('/login', 'Auth\LoginController@showLoginForm')->name('user.login');
    Route::post('/login', 'Auth\LoginController@login')->name('user.authenticate');
    Route::get('/logout', 'Auth\LoginController@logout')->name('user.logout');
    /*Route::get('/logout', 'Auth\LoginController@logout', function () {
        return abort(404);
    })->name('user.logout');*/

    Route::get('/', 'TeamController@index')->name('user.dashboard')->middleware('auth:web');
    Route::get('/team', 'TeamController@index')->name('user.team.index')->middleware('auth:web');
    Route::get('team/create', 'TeamController@create')->name('user.team.create')->middleware('auth:web');
    Route::post('team/store', 'TeamController@store')->name('user.team.store')->middleware('auth:web');
});




