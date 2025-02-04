<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\UsersMetadata;

class UserController extends Controller {

    public function AuthRegister(Request $request) {

        try {

            //Reglas de Validacion del Formulario Login
            $validate = $request->validate(
                [
                    'user_email'=>'required|email:rfc,dns',
                    'user_password'=>'required|min:2'
                ],
                [
                    'user_email.required'=>'El campo E-Mail está vacío',
                    'user_email.email'=>'El E-Mail ingresado no es válido',
                    'user_password.required'=>'El campo Contraseña está vacío',
                    'user_password.min'=>'El campo Contraseña debe tener al menos 6 caracteres'
                ]
            );

            //retorna pass encriptada
            //die (Hash::make($request->input('user_password'))); 
            
            if(Auth::attempt([
                'email'     =>$request->input('user_email'), 
                'password'  =>$request->input('user_password')
            ])){

                //Valida usuario  con la tabla "users_metadata"
                $usuario = UsersMetadata::where(['users_id'=>Auth::id()])->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso',
                    'user' => [
                        'user_id'     => $usuario->id,
                        'user_email'  => $usuario->user_email,
                        'user_name'   => $usuario->user_name,
                        'user_perfil' => $usuario->perfiles->nombre
                    ],
                ]);
            }
            else
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Error Authenticacion',
                    'user' => [],
                ],401);
            }
        }
        catch (\Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'user' => [],
            ],500);
        }
    }
    
}
