<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ChequeoCardiovascular;
use Illuminate\Support\Facades\Storage;


/****************************** 
* * Ejecutar Link Simbolico 
* * php artisan storage:link
*******************************/

class FileUploadController extends Controller {

    /****************************************************************************** 
    * * Copia el archivo en el servidor publico pero se debe crear la carpeta
    * * con un archivo  txt, de lo contrario usar funcion "uploadFileStorage"
    ******************************************************************************/

    public function FileUpload(Request $request) {

        $rut  = $request->rut;

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,png|max:5048'
        ],
        [
            'file.requierd' => 'No Existe Archivo',
            'file.mines' => 'El Archivo debe ser JPG|PNG'   
        ]);
    

        switch($_FILES['file']['type']){

            case 'image/png':
                $archivo = time().".png";
                break;
            case 'image/jpeg': 
                $archivo = time().".jpg";
                break;        
        }

        copy($_FILES['file']['tmp_name'],'Electrocardiograma/'.$archivo);

        $update = ChequeoCardiovascular::where(['rut' => $rut])->firstOrFail();
        $update->fileStatus = 'OK';
        $update->fileName = $archivo;
        $update->save();

        //return redirec()->route('formularioUpload');

        return response()->json([
            'success' => true,
            'message' => 'Archivo subido con éxito.',
        ]);

        
    }


    /****************************************************************************** 
    * * Copia el archivo en el servidor publico en la carpeta Storage, para usar esta 
    * * funcion debes ejecutar el path "/execute-link-simbolik" para realice el 
    * * link simbolico entre las carpetas "storage/app/public" => "public/storge"
    ******************************************************************************/

    public function FileUploadStorage(Request $request) {

        // Tamaño máximo 5MB, acepta imágenes y PDFs
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,png|max:5048'
        ],
        [
            'file.requierd' => 'No Existe Archivo',
            'file.mines' => 'El Archivo debe ser JPG|PNG'   
        ]);

        $file = $request->file('file');
        $rut  = $request->rut;

        // Definir el nombre y ruta donde se guardará
        //ergosanitas-app\storage\app\public\uploads
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('Electrocardiograma', $fileName, 'public');

        //$filePath = $file->store('public/Electrocardiograma');


        $update = ChequeoCardiovascular::where(['rut' => $rut])->firstOrFail();
        $update->fileStatus = 'OK';
        $update->fileName = $fileName;
        $update->save();


        // Guardar o retornar la ruta para realizar otras operaciones
        return response()->json([
            'success' => true,
            'file' => $filePath,
            'message' => 'Archivo subido con éxito.',
        ]);
       

    }
  
    
}
