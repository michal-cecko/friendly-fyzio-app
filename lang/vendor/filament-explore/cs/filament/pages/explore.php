<?php

return [
    'actions' => [
        'upload' => [
            'label' => 'Nahrát',
            'modal_heading' => 'Nahrát soubory',
            'modal_description' => 'Přetáhněte sem nebo klikněte pro nahrání nových položek do knihovny.',
            'modal_cancel_action_label' => 'Zavřít',
        ],
    ],
    'exploreForm' => [
        'components' => [
            'filters' => [
                'schema' => [
                    'search' => [
                        'label' => 'Hledat',
                        'placeholder' => 'Hledat soubory nebo složky podle názvu',
                    ],
                    'tabs' => [
                        'label' => 'Filtry',
                    ],
                    'trigger' => [
                        'filters' => [
                            'label' => 'Filtr',
                        ],
                    ],
                    'sorters' => [
                        'label' => 'Řadit ',
                    ],
                    'display_options' => [
                        'label' => 'Zobrazení',
                        'actions' => [
                            'update_file_version_grid' => [
                                'label' => 'Ikony',
                            ],
                            'update_file_version_row' => [
                                'label' => 'Seznam',
                            ],
                            'toggle_file_is_file_extension_visible' => [
                                'label' => [
                                    'true' => 'Skrýt přípony',
                                    'false' => 'Zobrazit všechny přípony souborů',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'files' => [
                'empty_state' => [
                    'heading' => [
                        'not_found' => 'Žádné soubory nenalezeny',
                        'not_found_with_modifications' => 'Vašemu hledání nic neodpovídá',
                        'unauthorized' => 'Nemáte oprávnění zobrazit tuto složku',
                    ],
                    'description' => [
                        'not_found' => 'Začněte nahráním své první položky.',
                        'not_found_with_modifications' => 'Zkuste změnit filtry nebo vyhledat jiný výraz.',
                        'unauthorized' => 'Pokud si myslíte, že jde o chybu, kontaktujte svého administrátora.',
                    ],
                ],
            ],
            'selected_file' => [
                'empty_state_heading' => 'Zatím nebyl vybrán žádný soubor',
                'schema' => [
                    'information' => [
                        'heading' => 'Informace',
                        'schema' => [
                            'uploader' => [
                                'label' => 'Nahrál',
                            ],
                            'created_at' => [
                                'label' => 'Nahráno',
                                'placeholder' => '-',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
