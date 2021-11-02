<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\TenantMigrate::class,
        Commands\ImportFeeCsv::class,
        Commands\FillProviderArticlesWithStock::class,
        Commands\ExportZalandoCSV::class,
        Commands\ImportFeeCsv2::class,
        Commands\ImportFeeStockCsv2::class
    ];

    protected function manageContent(Schedule $schedule){

        //Mayer Burghausen
        $schedule->command('vsshop:send_syncros mayer-burghausen scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros mayer-burghausen immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //Fashion und Trends
        $schedule->command('vsshop:send_syncros fashionundtrends scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros fashionundtrends immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //modemai
        $schedule->command('vsshop:send_syncros modemai scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros modemai immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //melchior
        $schedule->command('vsshop:send_syncros melchior scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros melchior immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //vanhauth
        $schedule->command('vsshop:send_syncros vanhauth scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros vanhauth immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //wildhardt
        $schedule->command('vsshop:send_syncros wildhardt scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros wildhardt immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

         //obermann
         $schedule->command('vsshop:send_syncros obermann scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
         $schedule->command('vsshop:send_syncros obermann immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //wunderschoen-mode
        $schedule->command('vsshop:send_syncros wunderschoen-mode scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros wunderschoen-mode immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //olgasmodewelt
        $schedule->command('vsshop:send_syncros olgasmodewelt scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros olgasmodewelt immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //frauenzimmer
        $schedule->command('vsshop:send_syncros frauenzimmer scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros frauenzimmer immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //schwoeppe
        $schedule->command('vsshop:send_syncros schwoeppe scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros schwoeppe immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

        //keller
        $schedule->command('vsshop:send_syncros keller scheduled')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('vsshop:send_syncros keller immediate')->everyTenMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();

    }

    protected function wixCommands(Schedule $schedule) {
        // Update Pictures
        $schedule->command('test:wix_shop_test neheim update_pictures')->everyOneMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
        $schedule->command('test:wix_shop_test neheim update_pictures_immediate')->everyOneMinutes()->unlessBetween('23:30', '1:30')->runInBackground()->withoutOverlapping();
    }
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('import:feecsv_neu false')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        // FEE
        $schedule->command('import:feecsv_startjob_kunde stilfaktor')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde melchior')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde vanhauth')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde dhtextil')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde fashionundtrends')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde fischer-stegmaier')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde mode-wittmann')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde wildhardt')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde modemai')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde wunderschoen-mode')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde senft')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde mukila')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde fruehauf')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde mayer-burghausen')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde obermann')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde pascha')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde cosydh')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde keller')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde haider')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde hl')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde sparwel')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde neheim')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde mehner')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde schwoeppe')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde plager')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde favors')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde fashionobermann')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde scheibe')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde modebauer')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde bstone')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde frauenzimmer')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde olgasmodewelt')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde pk-fashion')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde romeiks')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde velmo')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde 4youjeans')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde wehner')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde profashion')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde bernard')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde huthanne')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde stadler')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('import:feecsv_startjob_kunde stadlermax2')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();


        $schedule->command('import:feecsv2')->everyFiveMinutes()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();
        $schedule->command('import:feedelta false false')->everyFiveMinutes()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();
        $schedule->command('import:feedelta2')->everyFiveMinutes()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();

        //FashionCloud
        $schedule->command('import:fashioncloud')->everyThirtyMinutes()->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground(); //->cron('0 */4 * * *')
        $schedule->command('import:fashioncloud_kunde fashionundtrends')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde melchior')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde modemai')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde wildhardt')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde wunderschoen_mode')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde zoergiebel')->everyThirtyMinutes()->runInBackground();//->between('23:00', '5:00')->unlessBetween('8:00', '17:00')->runInBackground();
        $schedule->command('import:fashioncloud_kunde mayer_burghausen')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde obermann')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde schwoeppe')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde vanhauth')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde frauenzimmer')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde neheim')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde keller')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde bernard')->everyThirtyMinutes()->runInBackground();
        $schedule->command('import:fashioncloud_kunde senft')->everyThirtyMinutes()->runInBackground();

        //Fashioncloud - Anpassung der Bilder in den Dimensionen
        //um Mitternacht!
        $schedule->command('fc:check_resize_pictures')->dailyAt('01:00')->withoutOverlapping();

        // Zalando
        $schedule->command('export:zalandocsv false')->hourly()->unlessBetween('23:00', '1:00')->runInBackground();
        // VS Shop
        $schedule->command('export:stockbatches')->everyMinute()->withoutOverlapping()->unlessBetween('23:30', '1:30')->runInBackground();
        //$schedule->command('export:articlebatches')->everyThirtyMinutes()->unlessBetween('23:30', '1:30')->runInBackground();
        $schedule->command('export:pricebatches')->everyFourHours()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();
        //$schedule->command('export:shopvalidatedata')->cron('0 */4 * * *')->withoutOverlapping()->runInBackground();
        // IND VS Shop
        //$schedule->command('export:shopdata')->hourly()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();
        // Amazon
        $schedule->command('export:amazonproductfeed')->cron('0 */4 * * *')->runInBackground(); // ->dailyAt('16:30')
        $schedule->command('export:amazoninventoryfeed')->cron('0 */4 * * *')->runInBackground(); // ->dailyAt('17:01')
        $schedule->command('export:amazonpricesfeed')->cron('0 */4 * * *')->runInBackground(); // ->dailyAt('17:20')
        // Shopware
        $schedule->command('import:sworders')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        $schedule->command('shopware:imageupdate')->everyFiveMinutes()->runInBackground();
        //Shopware - Artikel-UPdate!!!!
        $schedule->command('export:shopware_batch')->hourly()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();//->dailyAt('03:01')->runInBackground();
        //Shopware - Artikel-Neuanlage!!!
        $schedule->command('fill:shopware')->hourly()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();

        // Adverics
        $schedule->command('import:advaricsarticles')->cron('0 */4 * * *')->withoutOverlapping()->runInBackground();
        $schedule->command('import:advarics_stockupdate')->hourly()->unlessBetween('23:30', '1:30')->withoutOverlapping()->runInBackground();

        //Check24
        $schedule->command('import:check24_import_order_all_tenants')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('export:check24_export_productfeed_all_tenants')->hourly()->withoutOverlapping();


        /* OUTDATED */
        //$schedule->command('import:feecsvold')->cron('0 */4 * * *')->withoutOverlapping()->runInBackground();
        //$schedule->command('import:feestock')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        //$schedule->command('import:feestock')->everyFifteenMinutes()->runInBackground();
        //$schedule->command('import:feecsv false')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

        //Alle Syncros löschen und die Übertragung der Shopdaten neu starten
        $schedule->command('export:article_fill zoergiebel 1 --file_output')->cron('30 00 18 05 *')->runInBackground();


        //Content-Management, die Syncro zu den VSShops einbinden
        $this->manageContent($schedule);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        $this->load(__DIR__.'/Commands/Manager/Plattform');

        require base_path('routes/console.php');
    }
}
