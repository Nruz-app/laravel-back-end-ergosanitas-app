<?php

namespace App\Services;

use App\Models\UsersMetadata;
use App\Models\User;

class UserMetadataService
{
    /**
     * Obtiene el ID del perfil de un usuario según su email.
     */
    public function getPerfilIdByEmail(string $userEmail): ?int
    {
        return UsersMetadata::where('user_email', $userEmail)
            ->value('perfiles_id'); // Obtiene directamente el valor
    }

    public function userSave($name,$email,$password,$perfiles_id) {


        $user           = new User;
        $user->name     = $name;
        $user->email    = $email;
        $user->password = bcrypt($password);
        $user->save();

        $idUser = $user->id;

        $userMetadata = new UsersMetadata;
        $userMetadata->users_id    =  $idUser;
        $userMetadata->perfiles_id =  $perfiles_id;
        $userMetadata->user_name   =  $name;
        $userMetadata->user_email  =  $email;
        $userMetadata->status      =  'S';
        $userMetadata->password    =  $password;
        $userMetadata->save();
    }
    public function UserUpdatePassowrd($password_user,$email_user) {

        try {

            $user = User::where('email', $email_user)->firstOrFail();
            $user->password = bcrypt($password_user);
            $user->save();

            $usersMetadata = UsersMetadata::where('user_email', $email_user)->firstOrFail();
            $usersMetadata->password = $password_user;
            $usersMetadata->save();

            return true;
        } catch (\Exception $e) {
            return null;
        }
    }
}
