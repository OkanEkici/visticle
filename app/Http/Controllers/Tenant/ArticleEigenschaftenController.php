<?php

namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;

use App\Tenant\Article;
use App\Tenant\Article_Eigenschaften;
use App\Tenant\Article_Eigenschaften_Articles;
use App\Tenant\Article_Eigenschaften_Data;
use Illuminate\Http\Request;

class ArticleEigenschaftenController extends Controller
{
    public function index()
    {
        $Eigenschaften = Article_Eigenschaften::all();
        $content = [];
        $counter = 0;
        foreach($Eigenschaften as $Eigenschaft)
        {   $counter++;
            $this_Attrs = $Eigenschaft->eigenschaften()->get();
            $EigenschaftenOptionen="";
            foreach($this_Attrs as $this_Attr)
            { $EigenschaftenOptionen.='<li data-id="'.$this_Attr->id.'" class="dd-item hiddenItem list-group-item p-0 border-0" style="display:none;">'
                .'<div class="dd-handle p-0 pl-3"><span class="drag-indicator"></span>'
                .'<div class="dd-nodrag btn-group m-0 w-100"><input type="text" value="'.$this_Attr->value.'" name="attr['.$Eigenschaft->id.'][]" class="attr-input form-control"><button class="delete_eigenschaft_attr btn btn-outline-danger" type="button">entfernen</button></div>'
                .'</div>'
                .'</li>'; }
            
            $content[] = [
                '<div class="custom-control custom-checkbox mb-1"><input '.((isset($Eigenschaft->active) && $Eigenschaft->active)?"checked":"").' data-id="'.$Eigenschaft->id.'" type="checkbox" class="custom-control-input" id="attribut['.$Eigenschaft->id.'][active]" name="attribut['.$Eigenschaft->id.'][active]"><label class="custom-control-label" for="attribut['.$Eigenschaft->id.'][active]"></label></div>',
                '<input name="attribut['.$Eigenschaft->id.'][name]" value="'.((isset($Eigenschaft->name))?$Eigenschaft->name:"").'" class="form-control" >'
                .'<div id="nestable'.$counter.'" class="dd" data-toggle="nestable" data-group="1" data-max-depth="1">'
                .'<ul class="dd-list list-group h-auto p-0 border-0 form-control"><li class="list-group-item p-0 border-0"><button class="neues_eigenschaft_attr btn btn-outline-primary mx-auto" type="button">hinzufügen</button><button class="showhide-Button btn btn-outline-secondary mx-l pull-right" type="button" onclick="$(this).parent().parent().find(\'.hiddenItem\').slideToggle();">Optionen anzeigen</button></li>'
                .$EigenschaftenOptionen.'</ul></div>',
                '<div class="custom-control custom-checkbox mb-1"><input '.((isset($Eigenschaft->is_filterable) && $Eigenschaft->is_filterable)?"checked":"").' data-id="'.$Eigenschaft->id.'" type="checkbox" class="custom-control-input" id="attribut['.$Eigenschaft->id.'][is_filterable]" name="attribut['.$Eigenschaft->id.'][is_filterable]"><label class="custom-control-label" for="attribut['.$Eigenschaft->id.'][is_filterable]"></label></div>',
                '<a data-id="'.$Eigenschaft->id.'" class="btn btn-sm btn-secondary save-attribute mr-3" >Speichern</a>'
                .'<a data-id="'.$Eigenschaft->id.'" class="btn btn-sm btn-icon btn-secondary text-red delete-attribute" ><i class="far fa-trash-alt"></i></a>'
            ];
        } 
        $content[] = [
            '<div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="new_active" name="new_active" value="1"><label class="custom-control-label" for="new_active"></label></div>',
            '<input id="new_name" name="new_name" value="" class="form-control" >'
            .'<div id="nestable'.$counter.'" class="dd" data-toggle="nestable" data-group="1" data-max-depth="1">'
            .'<ul class="dd-list list-group h-auto p-0 border-0 form-control"><li class="list-group-item p-0 border-0"><button class="neues_eigenschaft_attr btn btn-outline-primary mx-auto" type="button">hinzufügen</button></li></ul></div>',
            '<div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="new_is_filterable" name="new_is_filterable" value="1"><label class="custom-control-label" for="new_is_filterable"></label></div>',
            '<a class="btn btn-sm btn-secondary new-attribute mr-3" >erstellen</a>'
        ];
        return view('tenant.modules.article.index.eigenschaften', ['content'=>$content,'sideNavConfig' => Article::sidenavConfig('eigenschaftenverwaltung')]);
    }

    public function create(Request $request)
    {
        $articleEigenschaft = false;
        $data = $request->all();
        
        $name = $data['name'];
        $attributes = ((isset($data['attributes']))? $data['attributes'] : false);
        $is_filterable = $data['is_filterable']; $active = $data['active'];
        $articleEigenschaft = Article_Eigenschaften::where('name','=',$name)->first();
        if(!empty($name) && !$articleEigenschaft)
        {
            $articleEigenschaft = Article_Eigenschaften::updateOrCreate( 
            [ 'name' => $name
            , 'is_filterable' => (($is_filterable)? 1 : 0)
            , 'active' => (($active)? 1 : 0) ] );
    
            if($articleEigenschaft && is_array($attributes))
            {   foreach($attributes as $attribute)
                {   if(!empty($attribute['value']))
                    {
                        Article_Eigenschaften_Data::updateOrCreate( 
                        [ 'fk_eigenschaft_id' => $articleEigenschaft->id
                        , 'name' => $name, 'value' =>$attribute['value'] ]);
                    }
                }
            }
        }else{return json_encode(['error'=>'Fehler!']);}
        
        if($articleEigenschaft)
        { return json_encode(['success'=>'Erfolg!']); }
        else{return json_encode(['error'=>'Fehler!']);}
    }
    
    public function update(Request $request)
    {
        $Eigenschaft = false;
        $data = $request->all();
        
        $ID = $data['id'];        

        $name = $data['name'];
        $attributes = ((isset($data['attributes']))? $data['attributes'] : false);
        $is_filterable = $data['is_filterable']; $active = $data['active'];
        $Eigenschaft = Article_Eigenschaften::where('id','=',$ID)->first();
        $saveArtikelDatas = ['id'=>[],'article'=>[],'data_id'=>[],'data_name'=>[],'data_value'=>[]];
        
        if($ID != "" && $ID != false && $Eigenschaft)
        {
            $Eigenschaft = Article_Eigenschaften::updateOrCreate( 
                ['id' => $ID],
                [ 'name' => ((empty($name))? "" : $name)
                , 'is_filterable' => (($is_filterable)? 1 : 0)
                , 'active' => (($active)? 1 : 0) ] );
            
            $EigenschaftDatas = $Eigenschaft->eigenschaften()->get();
            $oldArtikelEigenschaftData=[];
            foreach($EigenschaftDatas as $EigenschaftData)
            {   
                $ArtikelBindungenIDs = Article_Eigenschaften_Articles::where('fk_eigenschaft_data_id','=',$EigenschaftData->id)->get()->pluck('fk_article_id')->toArray();
                
                if($ArtikelBindungenIDs)
                {
                    if(!in_array($EigenschaftData->value,$oldArtikelEigenschaftData))
                    {$oldArtikelEigenschaftData[$EigenschaftData->value]=[];}
                    $oldArtikelEigenschaftData[$EigenschaftData->value] = $ArtikelBindungenIDs;
                    $deleteEigenfschaftDataArticleBind = Article_Eigenschaften_Articles::where('fk_eigenschaft_data_id','=',$EigenschaftData->id)->delete(); 
                }                
            }
            $Eigenschaft->eigenschaften()->delete();
            
            if($Eigenschaft && is_array($attributes))
            {   foreach($attributes as $attribute)
                {   if(!empty($attribute['value']))
                    {   $newData = Article_Eigenschaften_Data::updateOrCreate( 
                        [ 'fk_eigenschaft_id' => $Eigenschaft->id, 'name' => $name, 'value' =>$attribute['value'] ]);

                        if(isset($oldArtikelEigenschaftData[$attribute['value']]) && count($oldArtikelEigenschaftData[$attribute['value']])>0 )
                        {   $newData->articles()->attach($oldArtikelEigenschaftData[$attribute['value']]); }
                    }
                }            
            }
        }        

        if($Eigenschaft)
        { return json_encode(['success'=>'Erfolg!']);}
        else{return json_encode(['error'=>'Fehler!']);}
    }
    
    public function delete(Request $request)
    {
        $articleEigenschaft = false;
        $data = $request->all();
        $ID = $data['id']; 
        
        if($ID != "" && $ID != false)
        {   $articleEigenschaft = Article_Eigenschaften::where('id', '=', $ID)->first();
            if($articleEigenschaft)
            { 
                // lösche Verbindungen
                $articleEigenschaft->categories()->delete();
                
                $DelDataIDs=[]; $articleEigenschaftDatas = $articleEigenschaft->eigenschaften()->get();
                foreach($articleEigenschaftDatas as $articleEigenschaftData){$DelDataIDs[]=$articleEigenschaftData->id;}
                Article_Eigenschaften_Articles::whereIn('fk_eigenschaft_data_id',$DelDataIDs)->delete();
                
                $articleEigenschaft->eigenschaften()->delete();
                
                $articleEigenschaft->delete();   
            } 
        }
        if($articleEigenschaft)
        { return json_encode(['success'=>'Erfolg!']); }
        else{return json_encode(['error'=>'Fehler!']);}
    }
}
