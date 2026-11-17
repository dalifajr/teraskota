<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Set some default values. It is possible to add all defines that can be set
    | in dompdf_config.inc.php. You can also override the entire config file.
    |
    */
    'show_warnings' => false,   // Throw an Exception on warnings from dompdf

    'public_path' => function_exists('public_path') && is_dir(public_path()) ? public_path() : (is_dir(base_path('public')) ? base_path('public') : base_path()),

    /*
     * Dejavu Sans font is missing glyphs for converted entities, turn it off if you need to show € and £.
     */
    'convert_entities' => true,

    'options' => [
        /**
         * The location of the DOMPDF font directory
         */
        'font_dir' => storage_path('fonts'),

        /**
         * The location of the DOMPDF font cache directory
         */
        'font_cache' => storage_path('fonts'),

        /**
         * The location of a temporary directory.
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * dompdf's "chroot": Prevents dompdf from accessing system files or other
         * files on the webserver.
         */
        'chroot' => array_filter([
            realpath(base_path()),
            function_exists('public_path') && is_dir(public_path()) ? realpath(public_path()) : null,
        ]),

        /**
         * Protocol whitelist
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,
        'log_output_file' => null,
        'enable_font_subsetting' => false,
        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',
        'default_font' => 'serif',
        'dpi' => 96,
        'enable_php' => false,
        'enable_javascript' => true,
        'enable_remote' => true,
        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],
];

