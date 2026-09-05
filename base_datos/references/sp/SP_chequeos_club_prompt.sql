CREATE PROCEDURE SP_chequeos_club_prompt(
    IN p_search VARCHAR(255),
    IN p_club VARCHAR(255)
)
BEGIN

    SELECT COALESCE(
        JSON_ARRAYAGG(
            JSON_OBJECT(

                'personales',
                JSON_OBJECT(
                    'rut', cc.rut,
                    'nombre', cc.nombre,
                    'edad', cc.edad,
                    'sexo_paciente', cc.sexo_paciente,
                    'fechaNacimiento', cc.fechaNacimiento
                ),

                'medicos',
                JSON_OBJECT(
                    'estatura', cc.estatura,
                    'peso', cc.peso,
                    'hemoglucotest', cc.hemoglucotest,

                    'presionArterial',
                    CASE
                        WHEN cc.presionArterial IS NOT NULL
                         AND cc.presion_sistolica IS NOT NULL
                        THEN CONCAT(
                            cc.presionArterial,
                            '/',
                            cc.presion_sistolica
                        )
                        ELSE NULL
                    END,

                    'saturacionOxigeno', cc.saturacionOxigeno,
                    'imc', cc.imc,
                    'gradoIncidenciaPosterio', cc.gradoIncidenciaPosterio,
                    'recuperacion', cc.Recuperacion,
                    'pulso', cc.pulso,
                    'medicamentosDiarios', cc.medicamentosDiarios
                ),

                'revision',
                JSON_OBJECT(
                    'frecuencia_cardiaca_paciente',
                        ec.frecuencia_cardiaca_paciente,

                    'observacion_paciente',
                        ec.observacion_paciente,

                    'estado_paciente',
                        ec.estado_paciente,

                    'derivacion_paciente',
                        ec.derivacion_paciente
                )

            )
        ),
        JSON_ARRAY()
    ) AS resultado_json

    FROM chequeo_cardiovascular cc

    INNER JOIN electro_cardiogranas ec
        ON ec.id_chequeo = cc.id
       AND ec.rut_paciente = cc.rut

    WHERE cc.status = 'REVISION MEDICA'
      AND cc.user_email = p_club
      AND (
            p_search IS NULL
            OR TRIM(p_search) = ''
            OR cc.rut LIKE CONCAT('%', TRIM(p_search), '%')
            OR cc.nombre LIKE CONCAT('%', TRIM(p_search), '%')
          );

END
