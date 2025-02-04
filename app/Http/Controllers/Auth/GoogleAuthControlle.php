<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Google\Client as GoogleClient;
use Google\Service\OAuth2;
use Illuminate\Support\Facades\Http;

class GoogleAuthControlle extends Controller {

    public function AuthLogin(Request $request) {

        try {

            // Obtener el token del cuerpo de la solicitud
            $token  = $request->token;

             // Configurar el cliente de Google
            $client = new GoogleClient();
            $client->setClientId( env('GOOGLE_CLIENT_ID') );
            $client->setClientSecret( env('GOOGLE_CLIENT_SECRET') );

            // Verificar el token de ID
            $payload = $client->verifyIdToken($token);

            if ($payload) {

                // ID de usuario de Google
                $googleId = $payload['sub'];

                // Correo electrónico del usuario 
                $email = $payload['email'];
                
                //Aquí puedes implementar la lógica para encontrar o crear un usuario en tu BDD

                return response()->json([
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso',
                    'user' => [
                        'google_id' => $googleId,
                        'email' => $email,
                    ],
                ]);

            }
            else {
                return response()->json(['success' => false, 'message' => 'Token inválido'], 401);
            }

        }
        catch (\Exception $e) {
            
            // Retorna una respuesta con el error
            $array = array('response' => array(
                'status' => 'Error en ejecucion',
                'mensaje' => $e->getMessage()));
        
            return response()->json($array,500);
        
        }

        

    }


    
}
