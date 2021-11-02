<?php

namespace App\Console\Commands\FashionCloud;

use Illuminate\Console\Command;
use App\Tenant;
use App\Tenant\Synchro, App\Tenant\Synchro_Type, App\Tenant\Synchro_Status;
use App\Tenant\Article;
use App\Tenant\Setting;
use App\Http\Controllers\Tenant\Providers\Fashioncloud\FashionCloudController;
use Config;
use App\Helpers\Miscellaneous;
use Illuminate\Support\Facades\Storage;

class CheckResizePictures extends Command
{
    protected $signature = 'fc:check_resize_pictures';
    protected $description = 'Passt die Bildergrößen für Fashioncloud an.';

    public function __construct(){parent::__construct();}

    public function handle()
    {
        $fc_main_folder='/fashioncloud/img/';

        $folders_list=[
            '200',
            '512',
            '1024'
        ];

        foreach($folders_list as $folder){
            $files=Storage::disk('public')->files($fc_main_folder . $folder);
            foreach($files as $file){
                $absolute_path=Storage::disk('public')->path($file);

                try{
                    $file_info=getimagesize($absolute_path);
                    if($file_info[1]!=$folder){
                        Miscellaneous::resizeImageHeight($absolute_path,$folder);
                    }
                }
                catch(\Exception $e){

                }

            }
        }
    }
}
