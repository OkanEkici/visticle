<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

use App\Tenant\Synchro;
use Illuminate\Http\Request;
use Storage;
use Redirect, Response;

class SynchroController extends Controller
{
    public function downloadFile($synchroId) {
        $synchro = Synchro::find($synchroId);
        if(!$synchro) {
            return redirect()->back()->withError('Synchronisation nicht gefunden');
        }
        else {
            $file= Storage::disk('customers')->path($synchro->filepath);
            $headers = [
				'Content-type'        => 'text/csv',
				'Content-Disposition' => 'attachment; filename="'.basename($file).'"',
			];
			return response()->download($file,basename($file),$headers);
        }

    }
}
