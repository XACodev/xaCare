<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Países soportados para nacionalidad/lugar de nacimiento
    |--------------------------------------------------------------------------
    */
    'countries' => [
        'GT' => ['name' => 'Guatemala', 'nationality' => 'Guatemalteco/a'],
        'MX' => ['name' => 'México', 'nationality' => 'Mexicano/a'],
        'SV' => ['name' => 'El Salvador', 'nationality' => 'Salvadoreño/a'],
        'HN' => ['name' => 'Honduras', 'nationality' => 'Hondureño/a'],
        'NI' => ['name' => 'Nicaragua', 'nationality' => 'Nicaragüense'],
        'CR' => ['name' => 'Costa Rica', 'nationality' => 'Costarricense'],
        'PA' => ['name' => 'Panamá', 'nationality' => 'Panameño/a'],
        'CO' => ['name' => 'Colombia', 'nationality' => 'Colombiano/a'],
        'VE' => ['name' => 'Venezuela', 'nationality' => 'Venezolano/a'],
        'EC' => ['name' => 'Ecuador', 'nationality' => 'Ecuatoriano/a'],
        'PE' => ['name' => 'Perú', 'nationality' => 'Peruano/a'],
        'BO' => ['name' => 'Bolivia', 'nationality' => 'Boliviano/a'],
        'CL' => ['name' => 'Chile', 'nationality' => 'Chileno/a'],
        'AR' => ['name' => 'Argentina', 'nationality' => 'Argentino/a'],
        'BR' => ['name' => 'Brasil', 'nationality' => 'Brasileño/a'],
        'US' => ['name' => 'Estados Unidos', 'nationality' => 'Estadounidense'],
        'CA' => ['name' => 'Canadá', 'nationality' => 'Canadiense'],
        'ES' => ['name' => 'España', 'nationality' => 'Español/a'],
        'CU' => ['name' => 'Cuba', 'nationality' => 'Cubano/a'],
        'DO' => ['name' => 'República Dominicana', 'nationality' => 'Dominicano/a'],
        'HT' => ['name' => 'Haití', 'nationality' => 'Haitiano/a'],
        'JM' => ['name' => 'Jamaica', 'nationality' => 'Jamaicano/a'],
        'BZ' => ['name' => 'Belice', 'nationality' => 'Beliceño/a'],
        'OTHER' => ['name' => 'Otro', 'nationality' => ''],
    ],

    /*
    |--------------------------------------------------------------------------
    | Departamentos y municipios de Guatemala
    |--------------------------------------------------------------------------
    | Lista de los 22 departamentos con sus cabeceras y municipios principales.
    | Se puede ampliar sin cambiar código.
    */
    'guatemala' => [
        'Alta Verapaz' => [
            'Cobán', 'San Pedro Carchá', 'San Juan Chamelco', 'Nebaj', 'Chahal',
            'Fray Bartolomé de las Casas', 'La Tinta', 'Panzós', 'Senahú', 'Cahabón',
        ],
        'Baja Verapaz' => [
            'Salamá', 'San Miguel Chicaj', 'Rabinal', 'Cubulco', 'Granados',
            'Santa Cruz El Chol', 'San Jerónimo', 'Purulhá',
        ],
        'Chimaltenango' => [
            'Chimaltenango', 'San José Poaquil', 'San Martín Jilotepeque', 'San Juan Comalapa',
            'Santa Apolonia', 'Tecpán Guatemala', 'Patzún', 'Pochuta', 'Patzicía',
            'Santa Cruz Balanyá', 'Acatenango', 'San Pedro Yepocapa', 'San Andrés Itzapa',
            'Parramos', 'Zaragoza', 'El Tejar',
        ],
        'Chiquimula' => [
            'Chiquimula', 'San José La Arada', 'San Juan Ermita', 'Jocotán', 'Camotán',
            'Olopa', 'Esquipulas', 'Concepción Las Minas', 'Quetzaltepeque', 'San Jacinto',
        ],
        'El Progreso' => [
            'Guastatoya', 'Morazán', 'San Agustín Acasaguastlán', 'San Cristóbal Acasaguastlán',
            'El Jícaro', 'Sansare', 'Sanarate', 'San Antonio La Paz',
        ],
        'Escuintla' => [
            'Escuintla', 'Santa Lucía Cotzumalguapa', 'La Democracia', 'Siquinalá',
            'Masagua', 'Tiquisate', 'La Gomera', 'Guanagazapa', 'San José',
            'Iztapa', 'Palín', 'San Vicente Pacaya', 'Nueva Concepción',
        ],
        'Guatemala' => [
            'Guatemala', 'Santa Catarina Pinula', 'San José Pinula', 'San José del Golfo',
            'Palencia', 'Chinautla', 'San Pedro Ayampuc', 'Mixco', 'San Pedro Sacatepéquez',
            'San Juan Sacatepéquez', 'San Raymundo', 'Chuarrancho', 'Fraijanes',
            'Amatitlán', 'Villa Nueva', 'Villa Canales', 'San Miguel Petapa',
        ],
        'Huehuetenango' => [
            'Huehuetenango', 'Chiantla', 'Malacatancito', 'Cuilco', 'Nentón',
            'San Pedro Necta', 'Jacaltenango', 'Soloma', 'Ixtahuacán', 'Santa Bárbara',
            'La Libertad', 'La Democracia', 'San Miguel Acatán', 'San Rafael La Independencia',
            'Todos Santos Cuchumatán', 'San Juan Atitán', 'Santa Eulalia', 'San Mateo Ixtatán',
            'Colotenango', 'San Sebastián Huehuetenango', 'Tectitán', 'Concepción Huista',
            'San Juan Ixcoy', 'San Antonio Huista', 'San Sebastián Coatlán', 'Barillas',
            'Aguacatán', 'San Rafael Petzal', 'San Gaspar Ixchil', 'Santiago Chimaltenango',
            'Santa Cruz Barillas', 'San Ildefonso Ixtahuacán',
        ],
        'Izabal' => [
            'Puerto Barrios', 'Livingston', 'El Estor', 'Morales', 'Los Amates',
        ],
        'Jalapa' => [
            'Jalapa', 'San Pedro Pinula', 'San Luis Jilotepeque', 'San Manuel Chaparrón',
            'San Carlos Alzatate', 'Monjas', 'Mataquescuintla',
        ],
        'Jutiapa' => [
            'Jutiapa', 'El Progreso', 'Santa Catarina Mita', 'Agua Blanca', 'Asunción Mita',
            'Yupiltepeque', 'Atescatempa', 'Jerez', 'El Adelanto', 'Zapotitlán',
            'Comapa', 'Jalpatagua', 'Conguaco', 'Moyuta', 'Pasaco', 'San José Acatempa',
            'Quesada',
        ],
        'Petén' => [
            'Flores', 'San José', 'San Benito', 'San Andrés', 'La Libertad', 'San Francisco',
            'Santa Ana', 'Dolores', 'San Luis', 'Sayaxché', 'Melchor de Mencos',
            'Poptún',
        ],
        'Quetzaltenango' => [
            'Quetzaltenango', 'Salcajá', 'Olintepeque', 'San Carlos Sija', 'Sibilia',
            'Cabricán', 'Cajolá', 'San Miguel Sigüilá', 'Ostuncalco', 'San Mateo',
            'Concepción Chiquirichapa', 'San Martín Sacatepéquez', 'Almolonga',
            'Cantel', 'Huitán', 'Zunil', 'Colomba', 'San Francisco La Unión',
            'El Palmar', 'Coatepeque', 'Génova', 'Flores Costa Cuca', 'La Esperanza',
            'Palestina de Los Altos',
        ],
        'Quiché' => [
            'Santa Cruz del Quiché', 'Chiché', 'Chinique', 'Zacualpa', 'Chajul',
            'Chichicastenango', 'Patzité', 'San Antonio Ilotenango', 'San Pedro Jocopilas',
            'Cunén', 'San Juan Cotzal', 'Joyabaj', 'Nebaj', 'San Andrés Sajcabajá',
            'Uspantán', 'Sacapulas', 'San Bartolomé Jocotenango', 'Canillá', 'Chicamán',
            'Ixcán', 'Pachalum',
        ],
        'Retalhuleu' => [
            'Retalhuleu', 'San Sebastián', 'Santa Cruz Muluá', 'San Martín Zapotitlán',
            'San Felipe', 'San Andrés Villa Seca', 'Champerico', 'Nuevo San Carlos',
            'El Asintal',
        ],
        'Sacatepéquez' => [
            'Antigua Guatemala', 'Jocotenango', 'Pastores', 'Sumpango', 'Santo Domingo Xenacoj',
            'Santiago Sacatepéquez', 'San Bartolomé Milpas Altas', 'San Lucas Sacatepéquez',
            'Santa Lucía Milpas Altas', 'Magdalena Milpas Altas', 'Santa María de Jesús',
            'Ciudad Vieja', 'San Miguel Dueñas', 'Alotenango', 'San Antonio Aguas Calientes',
            'Santa Catarina Barahona',
        ],
        'San Marcos' => [
            'San Marcos', 'San Pedro Sacatepéquez', 'San Antonio Sacatepéquez', 'Comitancillo',
            'San Miguel Ixtahuacán', 'Concepción Tutuapa', 'Tacaná', 'Sibinal',
            'Tajumulco', 'Tejutla', 'San Rafael Pie de la Cuesta', 'Nuevo Progreso',
            'El Tumbador', 'San José El Rodeo', 'Malacatán', 'Catarina', 'Ayutla',
            'Ocós', 'San Pablo', 'El Quetzal', 'La Reforma', 'Pajapita', 'Ixchiguán',
            'San José Ojetenam', 'San Cristóbal Cucho', 'Sipacapa', 'Esquipulas Palo Gordo',
            'Río Blanco', 'San Lorenzo',
        ],
        'Santa Rosa' => [
            'Cuilapa', 'Barberena', 'Santa Rosa de Lima', 'Casillas', 'San Rafael Las Flores',
            'Oratorio', 'San Juan Tecuaco', 'Chiquimulilla', 'Taxisco', 'Santa María Ixhuatán',
            'Guazacapán', 'Santa Cruz Naranjo', 'Pueblo Nuevo Viñas', 'Nueva Santa Rosa',
            'San Juan Alotenango',
        ],
        'Sololá' => [
            'Sololá', 'San José Chacayá', 'Santa María Visitación', 'Santa Lucía Utatlán',
            'Nahualá', 'Santa Catarina Ixtahuacán', 'Santa Clara La Laguna', 'Concepción',
            'San Andrés Semetabaj', 'Panajachel', 'Santa Catarina Palopó', 'San Antonio Palopó',
            'San Lucas Tolimán', 'Santa Cruz La Laguna', 'San Pablo La Laguna', 'San Marcos La Laguna',
            'San Juan La Laguna', 'San Pedro La Laguna',
        ],
        'Suchitepéquez' => [
            'Mazatenango', 'Cuyotenango', 'San Francisco Zapotitlán', 'San Bernardino',
            'San José El Ídolo', 'Santo Domingo Suchitepéquez', 'San Lorenzo',
            'Samayac', 'San Pablo Jocopilas', 'San Antonio Suchitepéquez', 'San Miguel Panán',
            'San Gabriel', 'Chicacao', 'Patulul', 'Santa Bárbara', 'San Juan Bautista',
            'Santo Tomás La Unión', 'Zunilito', 'Pueblo Nuevo', 'Río Bravo',
        ],
        'Totonicapán' => [
            'Totonicapán', 'San Cristóbal Totonicapán', 'San Francisco El Alto', 'San Andrés Xecul',
            'Momostenango', 'Santa María Chiquimula', 'Santa Lucía La Reforma', 'San Bartolo',
        ],
        'Zacapa' => [
            'Zacapa', 'Estanzuela', 'Río Hondo', 'Gualán', 'Teculután',
            'Usumatlán', 'Cabañas', 'San Diego', 'La Unión', 'Huité',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reglas de documento de identidad por país
    |--------------------------------------------------------------------------
    | type: etiqueta que se muestra en el formulario.
    | min / max: longitud del número.
    | regex: validación adicional opcional.
    */
    'documents' => [
        'GT' => [
            ['type' => 'CUI / DPI', 'min' => 13, 'max' => 13, 'regex' => '/^\d{13}$/'],
        ],
        'SV' => [
            ['type' => 'DUI', 'min' => 9, 'max' => 9],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'HN' => [
            ['type' => 'DNI', 'min' => 13, 'max' => 13],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'NI' => [
            ['type' => 'Cédula', 'min' => 14, 'max' => 14],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'CR' => [
            ['type' => 'Cédula de identidad', 'min' => 9, 'max' => 12],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'PA' => [
            ['type' => 'Cédula', 'min' => 4, 'max' => 20],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'MX' => [
            ['type' => 'CURP', 'min' => 18, 'max' => 18],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'CO' => [
            ['type' => 'Cédula de ciudadanía', 'min' => 6, 'max' => 12],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'US' => [
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
            ['type' => 'ID estatal', 'min' => 4, 'max' => 20],
        ],
        'CA' => [
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
            ['type' => 'ID provincial', 'min' => 4, 'max' => 20],
        ],
        'ES' => [
            ['type' => 'DNI', 'min' => 9, 'max' => 9],
            ['type' => 'Pasaporte', 'min' => 6, 'max' => 20],
        ],
        'OTHER' => [
            ['type' => 'Pasaporte', 'min' => 4, 'max' => 30],
            ['type' => 'ID nacional', 'min' => 4, 'max' => 30],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | País por defecto para pacientes guatemaltecos
    |--------------------------------------------------------------------------
    */
    'default_country' => 'GT',
];
