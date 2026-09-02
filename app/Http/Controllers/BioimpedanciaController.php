<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\IA\AnalisisBioimpedanciaPrompt;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\BioimpedanciaService;
use App\Models\Bioimpedancia;
use Mpdf\Mpdf;

class BioimpedanciaController extends Controller
{
    protected $bioimpedanciaService;

    public function __construct(BioimpedanciaService $bioimpedanciaService)
    {
        $this->bioimpedanciaService = $bioimpedanciaService;
    }

    public function ListBio()
    {
        try {
            $data = $this->bioimpedanciaService->listAll();

            return response()->json([
                'success' => true,
                'message' => 'Listado obtenido correctamente',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al listar bioimpedancias',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function FirstRut(Request $request)
    {
        $request->validate([
            'rut_paciente' => 'required|string',
        ]);

        try {
            $data = $this->bioimpedanciaService->firstByRut($request->rut_paciente);

            return response()->json([
                'success' => true,
                'message' => 'Listado obtenido correctamente',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al listar bioimpedancias',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function CreateBio(Request $request)
    {
        $request->validate([
            'rut' => 'required|string',
            'nombre' => 'required|string',
            'club' => 'required|string',
        ]);
        try {

            $nombre = $request->nombre;
            $club   = $request->club;

            $rutSave = str_replace('K', 'k', str_replace('.', '', $request->rut));

            $bio = $this->bioimpedanciaService->CreateBio(
                    $rutSave,
                    $nombre,
                    $club,
                );

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $bio
            ]);
        }
        catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error Crea Bioimpedancia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function FormUpload(Request $request)
    {
        // 1. VALIDACIÓN (Laravel ya lanza excepción si falla)
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png|max:5048',
            'rut' => 'required|string'
        ]);

        // 2. ARCHIVO
        $file = $request->file('file');

        $rut = str_replace(['.', '-'], '', $request->rut);
        $rutSave = str_replace('K', 'k', str_replace('.', '', $request->rut));

        $fileName = $rut . '_' . date('Hi') . '.' . $file->getClientOriginalExtension();

        $path = public_path('Bioimpedancia');

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $file->move($path, $fileName);

        $fullPath = $path . DIRECTORY_SEPARATOR . $fileName;

        // 3. IMAGEN
        $base64 = base64_encode(file_get_contents($fullPath));
        $mimeType = mime_content_type($fullPath);

        //
        $responsePac = $this->bioimpedanciaService->firstByRutChequeoBio($rutSave);

        // 4. PROMPT
        $systemPrompt = AnalisisBioimpedanciaPrompt::system();

        try {

            // 5. CLIENTE OPENAI (CORRECTO)
            //$clientIA = OpenAI::client(env('OPENAI_API_KEY'));

            // 6. REQUEST IA
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4.1-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Analiza esta imagen de bioimpedancia y devuelve SOLO JSON válido.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.1
            ]);

            // 7. RESPUESTA IA
            $aiContent = $response->choices[0]->message->content ?? null;

            $parsed = json_decode($aiContent, true);

           if (!$parsed || !is_array($parsed)) {

                $bio = new Bioimpedancia();
                $bio->rut = $rutSave;
                $bio->nombre = $responsePac->nombre??null;
                $bio->club   = $responsePac->user_email??null;
                $bio->raw_json = $aiContent; // Guarda el texto original
                $bio->archivo = $fileName;
                $bio->save();

                throw new \Exception("IA no devolvió JSON válido");
            }

            // 8. GUARDAR EN BASE DE DATOS
            $bio = $this->bioimpedanciaService->saveFromAI(
                $parsed,
                $rutSave,
                $responsePac->nombre??null,
                $responsePac->user_email??null,
                $fileName
            );

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $bio
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error procesando IA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function BioPDFRut(string $rut_paciente)
    {
        try {

            // 1. Obtener HTML desde el service
            $html = $this->bioimpedanciaService->BioPDFRut($rut_paciente);

            // 2. Crear PDF
            $mpdf = new Mpdf();

            $mpdf->WriteHTML($html);

            // 3. Descargar como archivo
            return response(
                $mpdf->Output("Certificado-".$rut_paciente.".pdf", "S")
            )->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Certificado-'.$rut_paciente.'.pdf"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error procesando IA',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
