<?php

namespace App\Imports;

use App\Models\ChequeoCardiovascular;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

//WithHeadingRow => Ignora la primera fila de los titulos
class ChequeoImport implements ToModel,WithHeadingRow
{
    protected $user_email = '';
    private string $errorMsg = 'OK';
    private int $cantInser = 0;

    public function __construct($user_email){
        $this->user_email = $user_email;
    }

    private function formatearRut($rut){
        return preg_replace('/[^0-9kK-]/', '', $rut); // Elimina puntos y caracteres no válidos
    }


    private function validarRut($rut){
        return preg_match('/^\d{7,8}-[0-9kK]$/', $rut);
    }

    private function calculateAge($fechaNacimiento){
        return Carbon::parse($fechaNacimiento)->age;
    }
    public function getCantInser(): int {
        return $this->cantInser;
    }
    public function getErrorMsg(): string {
        return $this->errorMsg;
    }

    private function formatearSexo($sexo) {
        $sexo = strtoupper($sexo);

        return ($sexo === "M") ? "Masculino" : (($sexo === "F") ? "Femenino" : "Masculino");
    }

    private function formatearFecha($fecha) {
        // Verifica si la fecha es numérica (como en formato de fecha de Excel)
        if (is_numeric($fecha)) {
            // Convierte la fecha de Excel a un objeto DateTime y luego lo transforma a Carbon
            return Carbon::instance(Date::excelToDateTimeObject($fecha));
        } else {
            // Si la fecha es una cadena, crea un objeto Carbon desde el formato 'd-m-Y'
            return Carbon::createFromFormat('d/m/Y', $fecha);
        }
    }


    public function model(array $row)
    {
        try {
            $rut = $this->formatearRut($row['rut']);
            if (!$this->validarRut($rut)) {
                return null;
            }
            $fechaFormat = $this->formatearFecha($row['fecha_nacimiento']);

            $save = new ChequeoCardiovascular;
            $save->nombre            = ucwords(strtolower($row['nombre_completo']));
            $save->rut               = $rut;
            $save->fechaNacimiento   = $fechaFormat->toDateString();
            $save->sexo_paciente     = $this->formatearSexo($row['sexo']);
            $save->edad              = $this->calculateAge($fechaFormat);
            $save->division_paciente = isset($row['division']) ? (string) $row['division'] : '';
            $save->user_email        = $this->user_email;
            $save->save();
            $this->cantInser++;
        }
        catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            return null;
        }

    }
}
