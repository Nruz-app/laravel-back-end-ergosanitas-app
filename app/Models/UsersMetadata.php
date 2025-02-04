<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersMetadata extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'users_metadata';

    /************************************************************************************** 
    ** NOTA : Al NO generar las siguiente funciones (llaves), cuando el usuario intente 
    ** registrarse dara error en "$usuario->perfiles->nombre"  
    ****************************************************************************************/


    //Genera llave foránea de la tabla "user" a users_metadata (1 a M)
    public function users() {
        return $this->belongsTo(User::class);//belongsTo => De uno A muchos
    }

    //Genera llave foránea de la tabla "perfiles" a users_metadata (1 a M)
    public function perfiles() {
        return $this->belongsTo(Perfiles::class);
    }
}
