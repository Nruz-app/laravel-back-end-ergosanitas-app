<?php

namespace App\Services;

use App\Models\UsersMetadata;

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

}
