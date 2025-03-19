<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ChequeoCardiovascularService;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ChequeoImport; // Ensure this class exists in the specified namespace

class CargaMasivaController extends Controller
{
    protected $chequeoCardiovascularService;


    public function __construct(
        ChequeoCardiovascularService $chequeoCardiovascularService){
        $this->chequeoCardiovascularService = $chequeoCardiovascularService;
    }

    public function CargaMasivaExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        // Asegurar que el archivo se está cargando correctamente
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No se ha enviado ningún archivo.'], 400);
        }

        try {
            $chequeoImport = new ChequeoImport($request->user_email);
            Excel::import($chequeoImport, $request->file('file'));

            return response()->json([
                'status' => 200,
                'message' => $chequeoImport->getErrorMsg(),
                'cantidad' =>  $chequeoImport->getCantInser()
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 500,
                'message' => $e->getMessage(),
                'cantidad' =>  0
            ], 500);
        }

    }

}
