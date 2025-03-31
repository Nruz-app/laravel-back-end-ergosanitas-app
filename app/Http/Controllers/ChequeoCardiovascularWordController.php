<?php

namespace App\Http\Controllers;

use App\Services\ChequeoCardiovascularWordService;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\IOFactory;

class ChequeoCardiovascularWordController extends Controller
{
    //
    protected $chequeoCardiovascularWordService;

    // Inyectar servicios a través del constructor
    public function __construct(
        ChequeoCardiovascularWordService $chequeoCardiovascularWordService ){
        $this->chequeoCardiovascularWordService = $chequeoCardiovascularWordService;
    }


    public function ChequeoWord(int  $id_paciente) {

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Obtener el HTML del chequeo
        $html = $this->chequeoCardiovascularWordService->chequeoWord($id_paciente);

        // Convertir HTML a Word
        Html::addHtml($section, $html, false, false);

        // Definir el nombre del archivo
        $fileName = "documento_desde_html.docx";

        // Configurar la respuesta HTTP para la descarga directa
        $headers = [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
        ];

        // Enviar el documento al navegador sin guardarlo en el servidor
        $writer = IOFactory::createWriter($phpWord, "Word2007");

        return response()->streamDownload(function () use ($writer) {
            $writer->save("php://output");
        }, $fileName, $headers);

    }

}
