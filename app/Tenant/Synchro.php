<?php

namespace App\Tenant;

use Illuminate\Database\Eloquent\Model;

class Synchro extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'fk_synchro_type_id',
        'fk_synchro_status_id',
        'expected_count',
        'success_count',
        'failed_count',
        'filepath',
        'url',
        'start_date',
        'end_date',
        'add_data'
    ];

    public function status() {
        return $this->belongsTo(Synchro_Status::class, 'fk_synchro_status_id');
    }

    public function type() {
        return $this->belongsTo(Synchro_Type::class, 'fk_synchro_type_id');
    }

    public static function basicTable($content) {
        return [
            'legend' => 'Synchronisation',
            'form-group' => [
                [
                    'type' => 'table',
                    'tableData' => [
                        'firstColumnWidth' => 10,
                        'easyTable' => true,
                        'title' => null,
                        'tableId' => 'synchroTable',
                        'columns' => ['Datum','Uhrzeit', 'Art', 'Status', 'Datei'],
                        'content' => $content
                    ]     
                ]
              ],
        ];
    }

    public static function basicTableDataByType($type) {
        $rawdata = self::rawDataByType($type);
        $tableData = [];
        foreach($rawdata as $synchro) {
            $tableData[] = [
                date('d.m.Y', strtotime($synchro->created_at)),
                date('H:i:s', strtotime($synchro->created_at)),
                $synchro->type()->first()->name,
                $synchro->status()->first()->description,
                ($synchro->filepath) ? '<a href="'.route('tenant.synchro.download', [config()->get('tenant.identifier'), $synchro->id]).'">Datei herunterladen</a>' : '-'
            ];
        }

        return $tableData;
    }

    public static function rawDataByType($type) {
        $related = [];
        $rawdata = [];
        switch($type) {
            case 'fee':
                $related = ['fee_import_csv', 'fee_stock_update'];
            break;
            case 'fashioncloud':
                $related = ['fashioncloud_update'];
            break;
            case 'zalando':
                $related = ['zalando_csv_export'];
            break;
            case 'shop':
                $related = ['shop_content_update', 'shop_stock_update'];
            break;
            default:
            break;
        }

        foreach($related as $typeKey) {
            $synchros = self::allByTypeKey($typeKey);
            foreach($synchros as $synchro) {
                $rawdata[] = $synchro;
            }
        }

        $sorted = collect($rawdata)->sortByDesc('created_at')->values()->all();
        return $sorted;
    }

    public static function allByTypeKey($typeKey) {
        $type = Synchro_Type::where('key','=',$typeKey)->first();
        if(!$type) {
            return false;
        }
        $synchros = Synchro::where('fk_synchro_type_id','=',$type->id)->orderBy('created_at', 'desc')->limit(100)->get();
        return $synchros;
    }
}
