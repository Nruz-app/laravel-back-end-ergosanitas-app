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
use App\Http\Controllers\CartFlow\AdminCartFlowController;
use App\Http\Controllers\CartFlow\AgendaPedidoController;
use App\Http\Controllers\CartFlow\CarritoController;
use App\Http\Controllers\CartFlow\CatalogoController;

use App\Http\Controllers\EmailController;

use App\Http\Controllers\ClubAssistantController;

use App\Http\Controllers\OpenAIController;

use App\Http\Controllers\Auth\GoogleAuthControlle;

use App\Http\Controllers\Auth\UserController;

use App\Http\Controllers\CertificadoUrlController;

use App\Http\Controllers\ElectroCardiogramaController;

use App\Http\Controllers\EstadisticasController;

use App\Http\Controllers\CargaMasivaController;

use App\Http\Controllers\ChequeoCardiovascularWordController;

use App\Http\Controllers\IncidenciasController;

use App\Http\Controllers\BioimpedanciaController;

use App\Http\Controllers\FichaClinicaController;

use App\Http\Controllers\JuegoCartasController;

use App\Http\Controllers\HomeImagenController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//LoginAuth

Route::post('auth-login',[GoogleAuthControlle::class,'AuthLogin'])
    ->name('AuthLogin');

Route::post('auth-register',[UserController::class,'AuthRegister'])
    ->name('AuthRegister');

Route::post('login/create-user',[UserController::class,'CreateUser'])
    ->name('CreateUser');

Route::post('auth-register/load-logo',[UserController::class,'FileUpload'])
    ->name('FileUpload');

Route::get('auth-register/user_email/{perfil}',[UserController::class,'ListUserEmail'])
    ->name('ListUserEmail');

Route::post('user-save',[UserController::class,'userSave'])
    ->name('userSave');

Route::put('user-update-password',[UserController::class,'UserUpdatePassowrd'])
    ->name('UserUpdatePassowrd');

Route::put('user-update-ergo-pass',[UserController::class,'UserUpdateErgoPass'])
    ->name('UserUpdateErgoPass');

Route::post('user-first-ergo-pass',[UserController::class,'UserFirstErgoPass'])
    ->name('UserFirstErgoPass');

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

Route::post('sam-assistant/reset-patient',[OpenAIController::class,'ResetPatient'])
    ->name('ResetPatient');

//CHAT POR CLUB

Route::post('sam-assistant-club/as-question',[ClubAssistantController::class,'AsQuestionClubUseCase'])
    ->name('AsQuestionClubUseCase');

Route::post('sam-assistant-club/reset-search',[ClubAssistantController::class,'ResetSearch'])
    ->name('ResetSearch');

Route::post('GPT/asistente-voz',[OpenAIController::class,'AsistenteVoz'])
    ->name('AsistenteVoz');


Route::post('GPT/analisis-ecg',[OpenAIController::class,'AnalisisEcg'])
    ->name('AnalisisEcg');


Route::post('certificado/save-url',[CertificadoUrlController::class,'FileUploadCer'])
    ->name('FileUploadCer');

Route::post('certificado/path-url',[CertificadoUrlController::class,'PathUrlCertificado'])
    ->name('PathUrlCertificado');

Route::post('certificado/valida-certificado',[CertificadoUrlController::class,'ValidaCertificado'])
    ->name('ValidaCertificado');

Route::post('carga-masiva-ecg',[CertificadoUrlController::class,'CargaMasivaEcg'])
    ->name('CargaMasivaEcg');

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

Route::get('estadisticas/estadistica-pago-mensual',[EstadisticasController::class,'EstadisticaPagoMensual'])
    ->name('EstadisticaPagoMensual');

Route::get('estadisticas/estadistica-pago-mdc',[EstadisticasController::class,'EstadisticaPagoMDC'])
    ->name('EstadisticaPagoMDC');

Route::post('estadisticas/pago-mensual',[EstadisticasController::class,'PagoMensual'])
    ->name('PagoMensual');

Route::post('estadisticas/delete-pago-mensual',[EstadisticasController::class,'DeletePagoMensual'])
    ->name('DeletePagoMensual');

Route::post('estadisticas/agenda-mensual',[EstadisticasController::class,'AgendaMensual'])
    ->name('AgendaMensual');

Route::post('/estadisticas/update-pago-mensual',[EstadisticasController::class,'UpdatePagoMensual'])
    ->name('UpdatePagoMensual');

Route::post('carga-masiva/excel',[CargaMasivaController::class,'CargaMasivaExcel'])
    ->name('CargaMasivaExcel');

Route::get('chequeo-cardiovascular-word/{id_paciente}',[ChequeoCardiovascularWordController::class,'ChequeoWord'])
    ->name('ChequeoWord');

// API - Incidencias

Route::post('incidencia-deportivos/create',[IncidenciasController::class,'IncidenciaCreate'])
    ->name('IncidenciaCreate');

Route::get('incidencia-deportivos/find-by-user/{user_email}',[IncidenciasController::class,'FindByUserEmail'])
    ->name('FindByUserEmail');

Route::get('incidencia-deportivos/count-club/{user_email}',[IncidenciasController::class,'CountClub'])
    ->name('CountClub');

Route::get('incidencia-deportivos/count-gravedad/{user_email}',[IncidenciasController::class,'CountGravedad'])
    ->name('CountGravedad');

Route::get('incidencia-deportivos/count-liga/{user_email}',[IncidenciasController::class,'CountLiga'])
    ->name('CountLiga');

Route::get('incidencia-deportivos/count-liga/{user_email}',[IncidenciasController::class,'CountLiga'])
    ->name('CountLiga');

Route::get('incidencia-deportivos/lesion-frecuente/{user_email}',[IncidenciasController::class,'LesionFrecuente'])
    ->name('LesionFrecuente');

Route::get('incidencia-deportivos/liga-casos/{user_email}',[IncidenciasController::class,'LigaCasos'])
    ->name('LigaCasos');

Route::get('incidencia-deportivos/sp_estadistica_liga/{user_email}',[IncidenciasController::class,'sp_estadistica_liga'])
    ->name('sp_estadistica_liga');

Route::get('incidencia-deportivos/sp_estadistica_categoria/{user_email}',[IncidenciasController::class,'sp_estadistica_categoria'])
    ->name('sp_estadistica_categoria');

Route::get('incidencia-deportivos/sp_estadistica_lesiones/{user_email}',[IncidenciasController::class,'sp_estadistica_lesiones'])
    ->name('sp_estadistica_lesiones');

Route::get('incidencia-deportivos/sp_estadistica_parte_cuerpo/{user_email}',[IncidenciasController::class,'sp_estadistica_parte_cuerpo'])
    ->name('sp_estadistica_parte_cuerpo');

Route::get('incidencia-deportivos/sp_estadistica_lesiones_fechas/{user_email}',[IncidenciasController::class,'sp_estadistica_lesiones_fechas'])
    ->name('sp_estadistica_lesiones_fechas');

Route::post('chequeo-cardiovascular/chequeo-all',[ChequeoCardiovascularController::class,'ChequeoEmailAll'])
    ->name('ChequeoEmailAll');

Route::post('bioimpedancia/form-upload',[BioimpedanciaController::class,'FormUpload'])
    ->name('FormUpload');

Route::get('bioimpedancia/list-all',[BioimpedanciaController::class,'ListBio'])
    ->name('ListBio');

Route::post('bioimpedancia/first-rut',[BioimpedanciaController::class,'FirstRut'])
    ->name('FirstRut');

Route::get('bioimpedancia/pdfRut/{rut_paciente}',[BioimpedanciaController::class,'BioPDFRut'])
    ->name('BioPDFRut');

Route::post('bioimpedancia/create-bio',[BioimpedanciaController::class,'CreateBio'])
    ->name('CreateBio');


Route::get('ficha-clinica/{rut_paciente}',[FichaClinicaController::class,'FichaClinica'])
    ->name('FichaClinica');


// El orden importa: las rutas literales van antes de la que captura {user_email},
// o juego-cartas/niveles se resolveria con user_email = "niveles".
Route::get('juego-cartas/niveles',[JuegoCartasController::class,'Niveles'])
    ->name('Niveles');

Route::get('juego-cartas/detalle/{rut_paciente}',[JuegoCartasController::class,'CartaDetalle'])
    ->name('CartaDetalle');

Route::get('juego-cartas/{user_email}',[JuegoCartasController::class,'CartasClub'])
    ->name('CartasClub');


// SPEC 03 — Mantenedor de imagenes del Home. La literal home-imagenes/admin va antes de las
// rutas con {id}; y {id} se restringe a numeros para que "admin" nunca se resuelva como id.
Route::get('home-imagenes',[HomeImagenController::class,'Index'])
    ->name('HomeImagenesIndex');

Route::get('home-imagenes/admin',[HomeImagenController::class,'Admin'])
    ->name('HomeImagenesAdmin');

Route::post('home-imagenes',[HomeImagenController::class,'Store'])
    ->name('HomeImagenesStore');

Route::put('home-imagenes/{id}/activo',[HomeImagenController::class,'UpdateActivo'])
    ->whereNumber('id')
    ->name('HomeImagenesUpdateActivo');

Route::put('home-imagenes/{id}',[HomeImagenController::class,'Update'])
    ->whereNumber('id')
    ->name('HomeImagenesUpdate');

Route::delete('home-imagenes/{id}',[HomeImagenController::class,'Destroy'])
    ->whereNumber('id')
    ->name('HomeImagenesDestroy');


// SPEC 04 — Carro de servicios (servicio-cart-flow). Flujo paralelo al de servicios / agenda-horas /
// transbank, que siguen vivos. Literales antes de las rutas con parametro; {id} solo numeros.
Route::get('cart-flow/comunas', [CatalogoController::class, 'Comunas'])
    ->name('CartFlowComunas');

Route::get('cart-flow/catalogo', [CatalogoController::class, 'Index'])
    ->name('CartFlowCatalogo');

Route::get('cart-flow/catalogo/{sku}', [CatalogoController::class, 'Show'])
    ->name('CartFlowProducto');

Route::put('cart-flow/admin/productos/{sku}/visibilidad', [CatalogoController::class, 'UpdateVisibilidad'])
    ->name('CartFlowVisibilidad');

// SPEC 05 — Mantenedor «Carrito Compra» del Administrador. Literales antes de {numero}/{id};
// la descarga de la orden médica solo acepta una URL firmada y temporal (middleware signed).
Route::get('cart-flow/admin/resumen', [AdminCartFlowController::class, 'Resumen'])
    ->name('CartFlowAdminResumen');

Route::get('cart-flow/admin/productos', [AdminCartFlowController::class, 'Productos'])
    ->name('CartFlowAdminProductos');

Route::get('cart-flow/admin/cotizaciones', [AdminCartFlowController::class, 'Cotizaciones'])
    ->name('CartFlowAdminCotizaciones');

Route::put('cart-flow/admin/cotizaciones/{id}/estado', [AdminCartFlowController::class, 'CotizacionEstado'])
    ->whereNumber('id')
    ->name('CartFlowAdminCotizacionEstado');

Route::post('cart-flow/admin/ordenes/{id}/enlace', [AdminCartFlowController::class, 'OrdenEnlace'])
    ->whereNumber('id')
    ->name('CartFlowAdminOrdenEnlace');

Route::get('cart-flow/admin/ordenes/{id}/descarga', [AdminCartFlowController::class, 'OrdenDescarga'])
    ->whereNumber('id')
    ->middleware('signed')
    ->name('CartFlowOrdenDescarga');

Route::get('cart-flow/admin/pedidos', [AdminCartFlowController::class, 'Pedidos'])
    ->name('CartFlowAdminPedidos');

Route::get('cart-flow/admin/pedidos/{numero}', [AdminCartFlowController::class, 'Pedido'])
    ->name('CartFlowAdminPedido');

Route::put('cart-flow/admin/pedidos/{numero}/estado', [AdminCartFlowController::class, 'PedidoEstado'])
    ->name('CartFlowAdminPedidoEstado');

Route::get('cart-flow/agenda/bloques', [AgendaPedidoController::class, 'Bloques'])
    ->name('CartFlowAgendaBloques');

Route::match(['get', 'post'], 'cart-flow/webpay/retorno', [AgendaPedidoController::class, 'WebPayRetorno'])
    ->name('CartFlowWebPayRetorno');

Route::post('cart-flow/cotizaciones', [AgendaPedidoController::class, 'CotizacionStore'])
    ->name('CartFlowCotizacion');

Route::get('cart-flow/pedidos/{numero}', [AgendaPedidoController::class, 'PedidoShow'])
    ->name('CartFlowPedido');

Route::post('cart-flow/pedidos/{numero}/webpay', [AgendaPedidoController::class, 'WebPay'])
    ->name('CartFlowPedidoWebPay');

Route::post('cart-flow/carritos', [CarritoController::class, 'Store'])
    ->name('CartFlowCarritoStore');

Route::get('cart-flow/carritos/{uuid}', [CarritoController::class, 'Show'])
    ->whereUuid('uuid')
    ->name('CartFlowCarritoShow');

Route::put('cart-flow/carritos/{uuid}', [CarritoController::class, 'Update'])
    ->whereUuid('uuid')
    ->name('CartFlowCarritoUpdate');

Route::post('cart-flow/carritos/{uuid}/pacientes', [CarritoController::class, 'PacienteStore'])
    ->whereUuid('uuid')
    ->name('CartFlowPacienteStore');

Route::put('cart-flow/carritos/{uuid}/pacientes/{id}', [CarritoController::class, 'PacienteUpdate'])
    ->whereUuid('uuid')->whereNumber('id')
    ->name('CartFlowPacienteUpdate');

Route::post('cart-flow/carritos/{uuid}/pacientes/{id}/quitar', [CarritoController::class, 'PacienteQuitar'])
    ->whereUuid('uuid')->whereNumber('id')
    ->name('CartFlowPacienteQuitar');

Route::post('cart-flow/carritos/{uuid}/lineas', [CarritoController::class, 'LineaStore'])
    ->whereUuid('uuid')
    ->name('CartFlowLineaStore');

Route::put('cart-flow/carritos/{uuid}/lineas/{id}', [CarritoController::class, 'LineaUpdate'])
    ->whereUuid('uuid')->whereNumber('id')
    ->name('CartFlowLineaUpdate');

Route::post('cart-flow/carritos/{uuid}/lineas/{id}/quitar', [CarritoController::class, 'LineaQuitar'])
    ->whereUuid('uuid')->whereNumber('id')
    ->name('CartFlowLineaQuitar');

Route::post('cart-flow/carritos/{uuid}/checkout', [CarritoController::class, 'Checkout'])
    ->whereUuid('uuid')
    ->name('CartFlowCheckout');
