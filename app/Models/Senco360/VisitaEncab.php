<?php
namespace App\Models\Senco360;

use Illuminate\Database\Eloquent\Model;
use App\Models\RutaTecnica;

class VisitaEncab extends Model
{
    protected $connection = 'senco360';
    protected $table = 'RT_visita_encab';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'ID_VISITA', 'CORREO', 'HORA_INICIO', 'HORA_FIN',
        'FECHA_INICIO', 'FECHA_FIN', 'FECHA_REPROGRAMACION',
        'ID_TIPO_SERVICIO', 'OBSERVACIONES', 'ID_ESTADO_ACTUAL',
        'DETALLE_PUBLICADO_COTIZACION', 'PROPIETARIOFIRMA',
        // ⚠️ FIRMA_CLIENTE omitida intencionalmente (varbinary, se maneja aparte)
    ];

    public function rutaTecnica()
    {
        return $this->belongsTo(RutaTecnica::class, 'ID_VISITA', 'IdVisita');
    }

    public function tipoServicio()
    {
        return $this->belongsTo(TipoServicio::class, 'ID_TIPO_SERVICIO', 'ID');
    }

    public function estadoActual()
    {
        return $this->belongsTo(VisitaEstado::class, 'ID_ESTADO_ACTUAL', 'ID');
    }

    public function detalle()
    {
        return $this->hasMany(VisitaDetal::class, 'ID_ENC_VISITA', 'ID');
    }

    public function historialEstados()
    {
        return $this->hasMany(VisitaEstadoHistorico::class, 'ID_ENC_VISITA', 'ID');
    }
}
