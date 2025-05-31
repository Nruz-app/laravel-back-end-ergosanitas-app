<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Importar
use App\Http\Controllers\AgendaHorasController;
use App\Http\Controllers\ChequeoCardiovascularController;
use App\Http\Controllers\FileUploadController;

use App\Http\Controllers\ServiciosController;
use App\Http\Controllers\WebPayController;

use App\Http\Controllers\EmailController;

use App\Http\Controllers\OpenAIController;

use App\Http\Controllers\Auth\GoogleAuthControlle;

use App\Http\Controllers\Auth\UserController;

use App\Http\Controllers\CertificadoUrlController;

use App\Http\Controllers\ElectroCardiogramaController;

use App\Http\Controllers\EstadisticasController;

use App\Http\Controllers\CargaMasivaController;

use App\Http\Controllers\ChequeoCardiovascularWordController;

use App\Http\Controllers\IncidenciasController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//LoginAuth

Route::post('auth-login',[GoogleAuthControlle::class,'AuthLogin'])
    ->name('AuthLogin');

Route::post('auth-register',[UserController::class,'AuthRegister'])
    ->name('AuthRegister');

Route::post('auth-register/load-logo',[UserController::class,'FileUpload'])
    ->name('FileUpload');

Route::get('auth-register/user_email',[UserController::class,'ListUserEmail'])
    ->name('ListUserEmail');

Route::post('user-save',[UserController::class,'userSave'])
    ->name('userSave');

Route::put('user-update-password',[UserController::class,'UserUpdatePassowrd'])
    ->name('UserUpdatePassowrd');

Route::get('servicios',[ServiciosController::class,'getService'])->name('getService');

Route::get('servicios/{name}',[ServiciosController::class,'showService'])->name('showService');

Route::post('servicios/like',[ServiciosController::class,'LikeServices'])->name('LikeServices');


//API Agendar Hora
Route::get('agenda-horas/health',[AgendaHorasController::class,'Health'])
    ->name('Health');

Route::get('agenda-horas',[AgendaHorasController::class,'getAgenda'])
    ->name('getAgenda');

Route::post('agenda-horas',[AgendaHorasController::class,'StoreAgenda'])
    ->name('StoreAgenda');


//
Route::get('chequeo-cardiovascular/health',[ChequeoCardiovascularController::class,'HealthCheck'])
    ->name('HealthCheck');


Route::get('chequeo-cardiovascular',[ChequeoCardiovascularController::class,'Index'])
    ->name('index');

Route::post('chequeo-cardiovascular/user',[ChequeoCardiovascularController::class,'FindByEmail'])
    ->name('FindByEmail');

Route::get('chequeo-cardiovascular/pdfRut/{rut_paciente}',[ChequeoCardiovascularController::class,'ChequeoPDFRut'])
    ->name('ChequeoPDFRut');

Route::get('chequeo-cardiovascular/pdf/{id_paciente}',[ChequeoCardiovascularController::class,'ChequeoPDF'])
    ->name('ChequeoPDF');

Route::get('chequeo-cardiovascular/{id_paciente}',[ChequeoCardiovascularController::class,'ChequeoRut'])
    ->name('ChequeoRut');

Route::post('chequeo-cardiovascular',[ChequeoCardiovascularController::class,'Store'])
    ->name('Store');

Route::put('chequeo-cardiovascular/{id_paciente}/{user_email}',[ChequeoCardiovascularController::class,'Update'])
    ->name('Update');


Route::post('chequeo-cardiovascular/like-chequeo',[ChequeoCardiovascularController::class,'LikeChequeo'])
    ->name('LikeChequeo');

Route::post('chequeo-cardiovascular/like-chequeo/user',[ChequeoCardiovascularController::class,'LikeChequeoUser'])
    ->name('LikeChequeoUser');

//Route::delete('chequeo-cardiovascular/{rut}',[ChequeoCardiovascularController::class,'DeleteRut'])
//    ->name('DeleteRut');

Route::delete('chequeo-cardiovascular/{id}',[ChequeoCardiovascularController::class,'deleteById'])
    ->name('deleteById');

Route::post('chequeo-cardiovascular/filter-calendar',[ChequeoCardiovascularController::class,'FilterCalendar'])
    ->name('FilterCalendar');

Route::get('chequeo-cardiovascular/estado-general/{user_email}',[ChequeoCardiovascularController::class,'EstadoGeneral'])
    ->name('EstadoGeneral');

Route::post('chequeo-cardiovascular/club-deportivo',[ChequeoCardiovascularController::class,'ChequeoUserEmail'])
    ->name('ChequeoUserEmail');

Route::post('chequeo-cardiovascular/search-chequeo',[ChequeoCardiovascularController::class,'SearchChequeo'])
    ->name('SearchChequeo');


Route::post('file-upload',[FileUploadController::class,'FileUploadRes'])
    ->name('FileUploadRes');

/*************************************************************************************************
* * Se Epone el siguiente path para ejecutar el comando para crear link simbolico para
* * sincronizar la carpeta "storage/app/public" => "public/storge" con el fin de poder exponer o
* * consumir los archivos del servidor expuesto a la nube
* * Comando (solo sirve en ambiente dev ya que prod no se puede ejecutar este comando)
* * php artisan storage:link
*************************************************************************************************/
Route::get('/execute-link-simbolik',function() {

    Artisan::call('storage:link');
    return 'storage-link-execute success';

});


Route::post('transbank/web-pay-request',[WebPayController::class,'WebPayRequest'])
    ->name('WebPayRequest');


Route::get('transbank/web-pay-response',[WebPayController::class,'WebPayResponse'])
    ->name('WebPayResponse');


Route::post('email/reserva-hora',[EmailController::class,'EmailReservaHora'])
    ->name('EmailReservaHora');

//CHATGPT-API

Route::post('sam-assistant/as-question',[OpenAIController::class,'AsQuestionUseCase'])
    ->name('AsQuestionUseCase');


Route::post('certificado/save-url',[CertificadoUrlController::class,'FileUploadCer'])
    ->name('FileUploadCer');

Route::get('certificado/validar/{rut_paciente}',[CertificadoUrlController::class,'ValidarRut'])
    ->name('ValidarRut');

Route::get('certificado/{rut_paciente}',[CertificadoUrlController::class,'showCertificado'])
    ->name('showCertificado');

Route::post('electro-cardiograma/find-by-rut',[ElectroCardiogramaController::class,'FindByRut'])
    ->name('FindByRut');

Route::post('electro-cardiograma/save',[ElectroCardiogramaController::class,'Save'])
    ->name('Save');

Route::get('estadisticas/estadistica-imc/{user_email}',[EstadisticasController::class,'EstadisticaIMC'])
    ->name('EstadisticaIMC');

Route::get('estadisticas/estadistica-presion/{user_email}',[EstadisticasController::class,'EstadisticaPresion'])
    ->name('EstadisticaPresion');

Route::get('estadisticas/estadistica-hemoglucotest/{user_email}',[EstadisticasController::class,'EstadisticaHemoglucotest'])
    ->name('EstadisticaHemoglucotest');

Route::get('estadisticas/estadistica-saturacion/{user_email}',[EstadisticasController::class,'EstadisticaSaturacion'])
    ->name('EstadisticaSaturacion');

Route::post('carga-masiva/excel',[CargaMasivaController::class,'CargaMasivaExcel'])
    ->name('CargaMasivaExcel');

Route::get('chequeo-cardiovascular-word/{id_paciente}',[ChequeoCardiovascularWordController::class,'ChequeoWord'])
    ->name('ChequeoWord');

// API - Incidencias

Route::post('incidencia-deportivos/create',[IncidenciasController::class,'IncidenciaCreate'])
    ->name('IncidenciaCreate');

Route::get('incidencia-deportivos/find-by-user/{user_email}',[IncidenciasController::class,'FindByUserEmail'])
    ->name('FindByUserEmail');

