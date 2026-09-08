-- SP_juego_cartas_club
--
-- Convierte a cada paciente de un club en una "carta" del juego: cuatro atributos
-- comparables 0-100, un puntaje total, estrellas, badge clinico y barra de completitud.
--
-- Reglas de negocio (ver specs/02-juego-cartas-niveles.md):
--   * Una carta por RUT, sobre su chequeo MAS RECIENTE.
--   * atributo = ROUND(100 * puntos_obtenidos / puntos_maximos_de_los_presentes).
--     Si ningun sub-indicador esta informado el atributo es NULL y la carta lo pinta "--".
--   * Sin ningun atributo medido -> puntaje NULL y banda -1 ("SIN EVALUAR"). Un alumno
--     cargado solo por Excel NO baja a BAJO por no tener datos.
--   * La bioimpedancia es un BONUS fuera del 100: no penaliza al 99,5% que no tiene el examen.
--   * Los umbrales y colores salen de juego_niveles, no de aqui: se retunean con un UPDATE.
--
-- Trampas de MySQL 5.7 respetadas en este archivo:
--   * CAST(NULL AS UNSIGNED) = 0 devuelve NULL, no TRUE -> todo CAST va con COALESCE(col,'0').
--   * chequeo_cardiovascular es VARCHAR de punta a punta -> todo CAST lleva rango de validez.
--   * El LEFT JOIN con electro_cardiogranas duplica cartas -> acotado con MAX(id) correlacionado.
--   * Sin CTEs, sin LATERAL y sin funciones de ventana (5.7.44).
--   * JSON_ARRAYAGG no respeta ORDER BY -> el orden por puntaje se hace en PHP (JuegoCartasService).

CREATE PROCEDURE SP_juego_cartas_club(
    IN p_search VARCHAR(255),
    IN p_club VARCHAR(255)
)
BEGIN

    SELECT COALESCE(
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'rut',               fin.rut,
                'nombre',            fin.nombre,
                'edad',              fin.edad,
                'sexo',              fin.sexo,
                'club',              fin.club,
                'id_chequeo',        fin.id_chequeo,
                'ficha',             CONCAT('#', LPAD(fin.id_chequeo, 6, '0')),
                'fecha_atencion',    fin.fecha_atencion,
                'total_chequeos',    fin.total_chequeos,

                'puntaje',           fin.puntaje,
                'estrellas',         fin.estrellas,

                'nivel',
                JSON_OBJECT(
                    'slug',         nc.slug,
                    'nombre',       nc.nombre,
                    'color_fondo',  nc.color_fondo,
                    'color_texto',  nc.color_texto
                ),

                'atributos',
                JSON_ARRAY(
                    JSON_OBJECT('slug', 'corazon',     'valor', fin.corazon,     'medido', fin.corazon     IS NOT NULL),
                    JSON_OBJECT('slug', 'vitalidad',   'valor', fin.vitalidad,   'medido', fin.vitalidad   IS NOT NULL),
                    JSON_OBJECT('slug', 'composicion', 'valor', fin.composicion, 'medido', fin.composicion IS NOT NULL),
                    JSON_OBJECT('slug', 'resistencia', 'valor', fin.resistencia, 'medido', fin.resistencia IS NOT NULL)
                ),
                'atributos_medidos', fin.atributos_medidos,

                'bonus',             fin.bonus,
                'insignia',          CASE WHEN fin.tiene_bio = 1 THEN 'InBody' ELSE NULL END,

                'bloques_completos', fin.bloques_completos,
                'progreso',          fin.bloques_completos * 20,

                'estado',
                JSON_OBJECT(
                    'slug',         ne.slug,
                    'nombre',       ne.nombre,
                    'color_fondo',  ne.color_fondo,
                    'color_texto',  ne.color_texto
                ),

                'completitud',
                JSON_OBJECT(
                    'chequeo',        TRUE,
                    'signos_vitales', fin.blq_signos = 1,
                    'ecg',            fin.blq_ecg = 1,
                    'bioimpedancia',  fin.blq_bio = 1,
                    'certificado',    fin.blq_cert = 1
                )
            )
        ),
        JSON_ARRAY()
    ) AS resultado_json

    FROM (
        -- ============ Capa 4: puntaje total, banda del badge y estrellas ============
        SELECT
            calc.rut, calc.nombre, calc.edad, calc.sexo, calc.club,
            calc.id_chequeo, calc.fecha_atencion, calc.total_chequeos,
            calc.corazon, calc.vitalidad, calc.composicion, calc.resistencia,
            calc.atributos_medidos, calc.bonus, calc.tiene_bio,
            calc.bloques_completos,
            calc.blq_signos, calc.blq_ecg, calc.blq_bio, calc.blq_cert,

            CASE WHEN calc.atributos_medidos = 0 THEN NULL
                 ELSE calc.total END AS puntaje,

            -- -1 es el centinela de "sin evaluar" en juego_niveles: el badge sigue
            -- saliendo de la tabla, asi nunca queda NULL.
            CASE WHEN calc.atributos_medidos = 0 THEN -1
                 ELSE calc.total END AS banda,

            CASE WHEN calc.atributos_medidos = 0 THEN 0
                 ELSE LEAST(5, GREATEST(1, CEIL(calc.total / 20))) END AS estrellas

        FROM (
            -- ============ Capa 3: atributos 0-100, bonus y puntaje total ============
            SELECT
                base.rut, base.nombre, base.edad, base.sexo, base.club,
                base.id_chequeo, base.fecha_atencion, base.total_chequeos,
                base.tiene_bio,
                base.blq_signos, base.blq_ecg, base.blq_bio, base.blq_cert,

                base.corazon, base.vitalidad, base.composicion, base.resistencia,
                base.atributos_medidos,
                base.bonus,

                1 + base.blq_signos + base.blq_ecg
                  + base.blq_bio + base.blq_cert AS bloques_completos,

                CASE WHEN base.atributos_medidos = 0 THEN 0
                     ELSE LEAST(100, ROUND(
                            ( COALESCE(base.corazon,0)     + COALESCE(base.vitalidad,0)
                            + COALESCE(base.composicion,0) + COALESCE(base.resistencia,0)
                            ) / base.atributos_medidos
                          ) + base.bonus)
                END AS total

            FROM (
                -- ============ Capa 2: los cuatro atributos (NULL si no hay dato) ============
                SELECT
                    src.rut, src.nombre, src.edad, src.sexo, src.club,
                    src.id_chequeo, src.fecha_atencion, src.total_chequeos,
                    src.tiene_bio,
                    src.blq_signos, src.blq_ecg, src.blq_bio, src.blq_cert,

                    CASE WHEN src.cor_max = 0 THEN NULL
                         ELSE ROUND(100 * src.cor_pts / src.cor_max) END AS corazon,

                    CASE WHEN src.vit_max = 0 THEN NULL
                         ELSE ROUND(100 * src.vit_pts / src.vit_max) END AS vitalidad,

                    -- Composicion mezcla el IMC con los cuatro indicadores de bioimpedancia:
                    -- cuando hay InBody el atributo es mas rico, no solo mas alto.
                    CASE WHEN (src.imc_max + src.bio_max) = 0 THEN NULL
                         ELSE ROUND(100 * (src.imc_pts + src.bio_pts)
                                        / (src.imc_max + src.bio_max)) END AS composicion,

                    CASE WHEN src.res_max = 0 THEN NULL
                         ELSE ROUND(100 * src.res_pts / src.res_max) END AS resistencia,

                    (src.cor_max > 0) + (src.vit_max > 0)
                        + ((src.imc_max + src.bio_max) > 0) + (src.res_max > 0) AS atributos_medidos,

                    -- Bonus 0-10 por la CALIDAD de la bioimpedancia, no por el mero hecho de tenerla.
                    CASE WHEN src.bio_max = 0 THEN 0
                         ELSE ROUND(10 * src.bio_pts / src.bio_max) END AS bonus

                FROM (
                    -- ============ Capa 1: puntos crudos y maximo disponible por atributo ============
                    SELECT
                        cc.rut,
                        cc.nombre,
                        cc.edad,
                        cc.sexo_paciente                       AS sexo,
                        cc.user_email                          AS club,
                        cc.id                                  AS id_chequeo,
                        cc.fecha_atencion,
                        (SELECT COUNT(*) FROM chequeo_cardiovascular cc2
                          WHERE cc2.rut = cc.rut)              AS total_chequeos,
                        CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END AS tiene_bio,

                        -- Corazon: estado ECG (50) + derivacion (30) + antecedente cardiovascular (20)
                        ( CASE WHEN ec.estado_paciente = 'Normal'   THEN 50
                               WHEN ec.estado_paciente = 'Alterado' THEN 15
                               ELSE 0 END
                        + CASE WHEN ec.id IS NULL THEN 0
                               WHEN LOWER(TRIM(COALESCE(ec.derivacion_paciente,''))) IN
                                    ('na','no','no requiere','') THEN 30
                               ELSE 8 END
                        + CASE WHEN LOWER(TRIM(COALESCE(cc.sistemaCardiovascular,''))) IN
                                    ('no presenta','sin alteraciones','sin ateraciones','ninguna','no') THEN 20
                               WHEN TRIM(COALESCE(cc.sistemaCardiovascular,'')) <> '' THEN 8
                               ELSE 0 END
                        )                                      AS cor_pts,
                        ( CASE WHEN ec.estado_paciente IN ('Normal','Alterado') THEN 50 ELSE 0 END
                        + CASE WHEN ec.id IS NOT NULL THEN 30 ELSE 0 END
                        + CASE WHEN TRIM(COALESCE(cc.sistemaCardiovascular,'')) <> '' THEN 20 ELSE 0 END
                        )                                      AS cor_max,

                        -- Vitalidad: saturacion de oxigeno (50) + presion arterial (50).
                        -- presionArterial guarda la DIASTOLICA y presion_sistolica la SISTOLICA:
                        -- se leen por su contenido real, no por su nombre (typo consolidado).
                        ( CASE WHEN CAST(COALESCE(cc.saturacionOxigeno,'0') AS UNSIGNED) NOT BETWEEN 50 AND 100 THEN 0
                               WHEN CAST(COALESCE(cc.saturacionOxigeno,'0') AS UNSIGNED) >= 95 THEN 50
                               WHEN CAST(COALESCE(cc.saturacionOxigeno,'0') AS UNSIGNED) >= 90 THEN 30
                               ELSE 10 END
                        + CASE WHEN CAST(COALESCE(cc.presion_sistolica,'0') AS UNSIGNED) NOT BETWEEN 60 AND 250 THEN 0
                               WHEN CAST(COALESCE(cc.presion_sistolica,'0') AS UNSIGNED) < 120
                                AND CAST(COALESCE(cc.presionArterial,'0') AS UNSIGNED) BETWEEN 30 AND 79 THEN 50
                               WHEN CAST(COALESCE(cc.presion_sistolica,'0') AS UNSIGNED) BETWEEN 120 AND 129
                                AND CAST(COALESCE(cc.presionArterial,'0') AS UNSIGNED) BETWEEN 30 AND 79 THEN 30
                               ELSE 10 END
                        )                                      AS vit_pts,
                        ( CASE WHEN CAST(COALESCE(cc.saturacionOxigeno,'0') AS UNSIGNED) BETWEEN 50 AND 100 THEN 50 ELSE 0 END
                        + CASE WHEN CAST(COALESCE(cc.presion_sistolica,'0') AS UNSIGNED) BETWEEN 60 AND 250 THEN 50 ELSE 0 END
                        )                                      AS vit_max,

                        -- Composicion, parte IMC (rango de validez 10-60: la columna es varchar)
                        ( CASE WHEN CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) NOT BETWEEN 10 AND 60 THEN 0
                               WHEN CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) BETWEEN 18.5 AND 24.9 THEN 100
                               WHEN CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) BETWEEN 17 AND 18.4
                                 OR CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) BETWEEN 25 AND 29.9 THEN 60
                               ELSE 20 END
                        )                                      AS imc_pts,
                        ( CASE WHEN CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) BETWEEN 10 AND 60 THEN 100 ELSE 0 END
                        )                                      AS imc_max,

                        -- Composicion, parte bioimpedancia. Tambien alimenta el bonus.
                        -- El sexo sale de bioimpedancia.sexo (Hombre/Mujer) con
                        -- chequeo_cardiovascular.sexo_paciente (Masculino/Femenino) de respaldo;
                        -- sin dato se usa el rango de Hombre.
                        ( CASE WHEN b.puntaje_corporal IS NULL OR b.puntaje_corporal <= 0 THEN 0
                               WHEN b.puntaje_corporal >= 80 THEN 100
                               WHEN b.puntaje_corporal >= 60 THEN 60
                               ELSE 20 END
                        + CASE WHEN b.grasa_corporal_pct IS NULL OR b.grasa_corporal_pct <= 0 THEN 0
                               WHEN LOWER(TRIM(COALESCE(b.sexo, cc.sexo_paciente, ''))) IN ('mujer','femenino','f')
                                THEN CASE WHEN b.grasa_corporal_pct BETWEEN 18 AND 28 THEN 100
                                          WHEN b.grasa_corporal_pct BETWEEN 13 AND 33 THEN 60
                                          ELSE 20 END
                               ELSE CASE WHEN b.grasa_corporal_pct BETWEEN 10 AND 20 THEN 100
                                         WHEN b.grasa_corporal_pct BETWEEN 5 AND 25 THEN 60
                                         ELSE 20 END
                               END
                        + CASE WHEN b.grasa_visceral IS NULL OR b.grasa_visceral <= 0 THEN 0
                               WHEN b.grasa_visceral < 10 THEN 100
                               WHEN b.grasa_visceral <= 14 THEN 60
                               ELSE 20 END
                        + CASE WHEN b.smi IS NULL OR b.smi <= 0 THEN 0
                               WHEN LOWER(TRIM(COALESCE(b.sexo, cc.sexo_paciente, ''))) IN ('mujer','femenino','f')
                                THEN CASE WHEN b.smi >= 5.7 THEN 100 ELSE 40 END
                               ELSE CASE WHEN b.smi >= 7.0 THEN 100 ELSE 40 END
                               END
                        )                                      AS bio_pts,
                        ( CASE WHEN b.puntaje_corporal   > 0 THEN 100 ELSE 0 END
                        + CASE WHEN b.grasa_corporal_pct > 0 THEN 100 ELSE 0 END
                        + CASE WHEN b.grasa_visceral     > 0 THEN 100 ELSE 0 END
                        + CASE WHEN b.smi                > 0 THEN 100 ELSE 0 END
                        )                                      AS bio_max,

                        -- Resistencia: hemoglucotest (40) + cuatro antecedentes generales (15 c/u)
                        ( CASE WHEN CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) NOT BETWEEN 30 AND 500 THEN 0
                               WHEN CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) BETWEEN 70 AND 140 THEN 40
                               WHEN CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) BETWEEN 60 AND 69
                                 OR CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) BETWEEN 141 AND 199 THEN 24
                               ELSE 8 END
                        + CASE WHEN LOWER(TRIM(COALESCE(cc.enfermedadesCronicas,''))) IN
                                    ('no presenta','sin alteraciones','sin ateraciones','ninguna','no') THEN 15
                               WHEN TRIM(COALESCE(cc.enfermedadesCronicas,'')) <> '' THEN 6 ELSE 0 END
                        + CASE WHEN LOWER(TRIM(COALESCE(cc.medicamentosDiarios,''))) IN
                                    ('no presenta','sin alteraciones','sin ateraciones','ninguna','no') THEN 15
                               WHEN TRIM(COALESCE(cc.medicamentosDiarios,'')) <> '' THEN 6 ELSE 0 END
                        + CASE WHEN LOWER(TRIM(COALESCE(cc.sistemaOsteoarticular,''))) IN
                                    ('no presenta','sin alteraciones','sin ateraciones','ninguna','no') THEN 15
                               WHEN TRIM(COALESCE(cc.sistemaOsteoarticular,'')) <> '' THEN 6 ELSE 0 END
                        + CASE WHEN LOWER(TRIM(COALESCE(cc.enfermedadesAnteriores,''))) IN
                                    ('no presenta','sin alteraciones','sin ateraciones','ninguna','no') THEN 15
                               WHEN TRIM(COALESCE(cc.enfermedadesAnteriores,'')) <> '' THEN 6 ELSE 0 END
                        )                                      AS res_pts,
                        ( CASE WHEN CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) BETWEEN 30 AND 500 THEN 40 ELSE 0 END
                        + CASE WHEN TRIM(COALESCE(cc.enfermedadesCronicas,''))   <> '' THEN 15 ELSE 0 END
                        + CASE WHEN TRIM(COALESCE(cc.medicamentosDiarios,''))    <> '' THEN 15 ELSE 0 END
                        + CASE WHEN TRIM(COALESCE(cc.sistemaOsteoarticular,''))  <> '' THEN 15 ELSE 0 END
                        + CASE WHEN TRIM(COALESCE(cc.enfermedadesAnteriores,'')) <> '' THEN 15 ELSE 0 END
                        )                                      AS res_max,

                        -- Eje 2: completitud de la ficha. El bloque 'chequeo' es siempre verdadero.
                        CASE WHEN CAST(COALESCE(cc.imc_paciente,'0') AS DECIMAL(6,2)) > 0
                              AND CAST(COALESCE(cc.presion_sistolica,'0') AS UNSIGNED) > 0
                              AND CAST(COALESCE(cc.presionArterial,'0') AS UNSIGNED) > 0
                              AND CAST(COALESCE(cc.saturacionOxigeno,'0') AS UNSIGNED) > 0
                              AND CAST(COALESCE(cc.hemoglucotest,'0') AS UNSIGNED) > 0
                             THEN 1 ELSE 0 END                 AS blq_signos,
                        CASE WHEN ec.id IS NOT NULL AND TRIM(COALESCE(ec.estado_paciente,'')) <> ''
                             THEN 1 ELSE 0 END                 AS blq_ecg,
                        CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END AS blq_bio,
                        CASE WHEN EXISTS (SELECT 1 FROM certificado_url cu
                                           WHERE cu.rut_paciente = cc.rut
                                             AND cu.id_chequeo = cc.id)
                             THEN 1 ELSE 0 END                 AS blq_cert

                    FROM chequeo_cardiovascular cc

                    -- Una carta por RUT: su chequeo mas reciente. Sin funciones de ventana en 5.7.
                    INNER JOIN (
                        SELECT rut,
                               CAST(SUBSTRING_INDEX(
                                   GROUP_CONCAT(id ORDER BY COALESCE(fecha_atencion, created_at) DESC, id DESC),
                                   ',', 1
                               ) AS UNSIGNED) AS id_ultimo
                        FROM chequeo_cardiovascular
                        WHERE (p_club IS NULL OR user_email = p_club)
                        GROUP BY rut
                    ) ult ON ult.id_ultimo = cc.id

                    -- Acotado con MAX(id): sin esto salen 1.561 cartas para 1.554 RUT.
                    LEFT JOIN electro_cardiogranas ec
                        ON ec.id_chequeo = cc.id
                       AND ec.rut_paciente = cc.rut
                       AND ec.id = (SELECT MAX(e2.id) FROM electro_cardiogranas e2
                                     WHERE e2.id_chequeo = cc.id AND e2.rut_paciente = cc.rut)

                    LEFT JOIN bioimpedancia b
                        ON b.rut = cc.rut
                       AND b.id = (SELECT MAX(b2.id) FROM bioimpedancia b2 WHERE b2.rut = cc.rut)

                    WHERE (p_search IS NULL
                           OR TRIM(p_search) = ''
                           OR cc.rut LIKE CONCAT('%', TRIM(p_search), '%')
                           OR cc.nombre LIKE CONCAT('%', TRIM(p_search), '%'))
                ) src
            ) base
        ) calc
    ) fin

    LEFT JOIN juego_niveles nc
        ON nc.tipo = 'clinico' AND nc.activo = 1
       AND fin.banda BETWEEN nc.valor_min AND nc.valor_max

    LEFT JOIN juego_niveles ne
        ON ne.tipo = 'completitud' AND ne.activo = 1
       AND fin.bloques_completos BETWEEN ne.valor_min AND ne.valor_max;

END
