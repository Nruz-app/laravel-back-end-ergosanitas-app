<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use App\Models\WebPayInfo;
use App\Models\LogsApi;

use Carbon\Carbon;
use App\Models\AgendaHoras;
use Illuminate\Support\Str;


class WebPayController extends Controller {


    public function WebPayRequest(Request $request) {
        
        try {

            $date = Carbon::now()->format('Ymd');
            $servicio = $request->servicios_name;
            $rut = $request->rut;
            $monto = $request->monto;

            $buy_order = $date.$servicio;
            $session_id = $rut;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Tbk-Api-Key-Id' => env ('WEBPAY_ID'),
                'Tbk-Api-Key-Secret' => env ('WEBPAY_SECRET')
            ])->post(env('WEBPAY_URL'),[
                'buy_order' => $buy_order,
                'session_id' => $session_id,
                'amount' => $monto,
                'return_url' => env ('WEBPAY_RETURN')
            ]);

            if($response->status() != 200) {

                $array = array('Response' => array(
                    'data' => 'Error al intentar pagar con WebPay'));
                
                return response()->json($array,500);
            }            
            
            $datos = json_decode($response);

            return response()->json($datos,200);

        }
        catch (\Exception $e) {

            $saveLogs = new LogsApi;
            $saveLogs->rutaWeb = 'WebPayRequest';
            $saveLogs->mensaje = Str::limit($e->getMessage(), 240);  
            $saveLogs->save();
        }  

        
    }

    public function WebPayResponse() {

        try {

            $tokenWs = $_GET['token_ws'];
        
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Tbk-Api-Key-Id' => env ('WEBPAY_ID'),
                'Tbk-Api-Key-Secret' => env ('WEBPAY_SECRET')
            ])->put(env('WEBPAY_URL')."/".$tokenWs,[]);

           
            $datos =  json_decode($response);

            $webPayInfo = new WebPayInfo;
        
            $webPayInfo->vci                 = $datos->vci;
            $webPayInfo->amount              = $datos->amount;
            $webPayInfo->status              = $datos->status;
            $webPayInfo->buy_order           = $datos->buy_order;
            $webPayInfo->session_id          = $datos->session_id;
            $webPayInfo->card_detail         = $datos->card_detail->card_number;
            $webPayInfo->accounting_date     = $datos->accounting_date;
            $webPayInfo->transaction_date    = $datos->transaction_date;
            $webPayInfo->authorization_code  = $datos->authorization_code;
            $webPayInfo->payment_type_code   = $datos->payment_type_code;
            $webPayInfo->response_code       = $datos->response_code;
            $webPayInfo->installments_number = $datos->installments_number;
            $webPayInfo->tokenWs             = $tokenWs; 
            $webPayInfo->rut_paciente        = $datos->session_id;
            $webPayInfo->save();    

            $rut_paciente =  $webPayInfo->rut_paciente;
            $agendaHoras = AgendaHoras::where(['rut' => $rut_paciente])->firstOrFail(); 
            
            $agendaHoras->pagado_paciente = 'PAGADO';
            $agendaHoras->save();    

            return redirect()->away('https://ergosanitas.com'); 
            //return redirect()->intended('/');
            // return redirect()->route('loginForm');
              
        }
        catch (\Exception $e) {

            $saveLogs = new LogsApi;
            $saveLogs->rutaWeb = 'WebPayResponse';
            $saveLogs->mensaje = Str::limit($e->getMessage(), 240);  
            $saveLogs->save();
        }  
          
    }
    
}
