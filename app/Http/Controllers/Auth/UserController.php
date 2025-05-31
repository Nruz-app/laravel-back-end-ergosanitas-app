<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Models\UsersMetadata;
use App\Services\UserMetadataService;

class UserController extends Controller {


    public function __construct(private UserMetadataService $userMetadataService) {
    }


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
                        'user_perfil' => $usuario->perfiles->nombre,
                        'user_logo'   => $usuario->user_logo
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
    public function FileUpload(Request $request) {

        $user_email   = $request->user_email;

        // Verificar si el archivo existe
        if ($request->hasFile('file') && $request->file('file')->isValid()) {

            // Obtener el archivo
            $file = $request->file('file');

            // Obtener la extensión del archivo
            $extension = $file->getClientOriginalExtension();

            // Definir la ruta de destino (Carpeta "Certificado")
            $destinationPath = public_path('Logo'); // o puedes usar 'storage_path('app/public/Certificado')'


            // Crear el nombre completo con el rut del paciente y la extensión
            $fileName = $user_email .'_'.uniqid().'.' . $extension;

            //
            $filePath = $destinationPath . '/' . $fileName;
            if (File::exists($destinationPath . '/' . $fileName)) {
                File::delete($filePath);
            }

            // Mover el archivo al destino
            $file->move($destinationPath, $fileName);


            $url_pdf=env('API_PATH_LOGO').'/'.$fileName;

            $chequeoCardiovascular = UsersMetadata::where(['user_email' => $user_email])->firstOrFail();
            $chequeoCardiovascular->user_logo = $url_pdf;
            $chequeoCardiovascular->save();

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido correctamente',
                'user_logo' => $url_pdf
            ]);

        }

    }

    public function ListUserEmail(int $perfil) {
        $responseUser = UsersMetadata::where('status', 'S')  // Verifica que 's' sea el valor correcto
        ->where('perfiles_id', $perfil)
        ->select('users_id','user_email', 'user_name')
        ->get(); // Devuelve una colección de resultados

    return response()->json($responseUser, 200);
    }

    public function userSave(Request $request) {

        try {

            $name        = $request->nombre_user;
            $email       = $request->email_user;
            $password    = $request->password_user;
            $perfiles_id = $request->perfil_user;

            $this->userMetadataService->userSave($name,$email,$password,$perfiles_id);

            return response()->json([
                'success' => true,
                'message' => 'OK'
            ],200);

        }
        catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],500);
        }
    }

    public function UserUpdatePassowrd(Request $request) {

        try {

            $password_user  = $request->password_user;
            $email_user     = $request->email_user;

            $this->userMetadataService->UserUpdatePassowrd($password_user,$email_user);

            return response()->json([
                'success' => true,
                'message' => 'OK'
            ],200);

        }
        catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],500);
        }
    }
}
