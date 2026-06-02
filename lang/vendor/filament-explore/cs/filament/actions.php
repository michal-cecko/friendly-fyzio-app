<?php

return [
    'upload_action' => [
        'label' => 'Nahrát',
        'modal_heading' => 'Nahrát soubory',
        'modal_description' => 'Přetáhněte sem nebo klikněte pro nahrání nových položek do knihovny.',
        'modal_cancel_action_label' => 'Zavřít',
        'modal_submit_action_label' => 'Uložit soubory',
        'success_notification_title' => '{1}:count soubor úspěšně nahrán|[2,*]:count souborů úspěšně nahráno',
        'schema' => [
            'files' => [
                'label' => 'Nahrát soubor',
                'below_content' => [
                    'text' => 'Můžete nahrát soubory až do velikosti :maxFileSizeFormatted.',
                ],
                'validation_messages' => [
                    'required' => 'Vyberte nebo přetáhněte soubor k nahrání.',
                ],
            ],
        ],
    ],
    'create_folder_action' => [
        'label' => 'Vytvořit složku',
        'modal_submit_action_label' => 'Uložit složku',
        'success_notification_title' => 'Složka :folderName úspěšně vytvořena',
        'schema' => [
            'name' => [
                'label' => 'Název složky',
                'below_content' => [
                    'text' => [
                        'allows_directory_separator_in_folder_name' => [
                            'true' => 'Přejděte do složky pro vytvoření podsložek.',
                            'false' => 'Pro vytvoření podsložek můžete použít `:directorySeparator`.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'delete_bulk_action' => [
        'label' => 'Smazat',
        'modal_heading' => 'Smazat 1 položku|Smazat :count položek',
        'modal_description' => [
            'with_folders' => '{1} Obsah vybrané složky bude trvale odstraněn. Opravdu to chcete provést?|[2,*] Obsah vybraných :count složek bude trvale odstraněn. Opravdu to chcete provést?',
            'without_folders' => 'Opravdu chcete tyto položky trvale smazat?',
        ],
        'modal_submit_action_label' => '{0} Smazat 0 položek|{1} Smazat 1 položku|[2,*] Smazat :count položek',
        'success_notification_title' => '{0} Žádné položky nesmazány|{1} 1 položka úspěšně smazána|[2,*] :count položek úspěšně smazáno',
    ],
    'move_bulk_action' => [
        'label' => 'Přesunout',
        'modal_heading' => 'Přesunout 1 položku|Přesunout :count položek',
        'modal_submit_action_label' => [
            'root' => '{0} Přesunout 0 položek|{1} Přesunout 1 položku|[2,*] Přesunout :count položek',
            'folder' => '{0} Přesunout 0 položek do :displayName|{1} Přesunout 1 položku do :displayName|[2,*] Přesunout :count položek do :displayName',
        ],
        'success_notification_title' => '{0} Žádné položky nepřesunuty|{1} 1 položka úspěšně přesunuta|[2,*] :count položek úspěšně přesunuto',
    ],
    'move_action' => [
        'label' => 'Přesunout',
        'modal_heading' => 'Přesunout :displayName',
        'modal_submit_action_label' => [
            'root' => 'Přesunout do kořene',
            'folder' => 'Přesunout do :displayName',
        ],
        'success_notification_title' => ':displayName úspěšně přesunuto',
        'failure_notification_title' => [
            'existing_target_folder_file' => 'Soubor s názvem :basename již existuje v :targetFolder',
            'root' => 'kořenová složka',
        ],
    ],
    'create_folder_bulk_action_bulk_action' => [
        'label' => 'Nová složka z výběru',
        'modal_heading' => 'Nová složka z výběru',
        'modal_submit_action_label' => '{0} Vytvořit složku a přesunout 0 položek|{1} Vytvořit složku a přesunout 1 položku|[2,*] Vytvořit složku a přesunout :count položek',
        'success_notification_title' => 'Složka :folderName úspěšně vytvořena s :count přesunutými položkami',
        'schema' => [
            'name' => [
                'label' => 'Název složky',
            ],
        ],
    ],
    'delete_action' => [
        'label' => 'Smazat',
        'modal_heading' => [
            'file' => 'Smazat soubor :displayName',
            'folder' => 'Smazat složku :displayName',
        ],
        'modal_description' => [
            'file' => 'Opravdu chcete tento soubor smazat?',
            'folder' => 'Žádné soubory ve složce nebudou smazány, ale přesunuty do aktuální složky. Opravdu chcete tuto složku smazat?',
        ],
        'success_notification_title' => [
            'file' => 'Soubor :displayName úspěšně smazán',
            'folder' => [
                'delete_content' => 'Složka :displayName úspěšně smazána, včetně veškerého obsahu',
                'preserve_content' => 'Složka :displayName úspěšně smazána',
            ],
        ],
        'schema' => [
            'delete_content' => [
                'label' => 'Smazat veškerý obsah ve složce',
                'below_content' => [
                    'text' => [
                        'true' => 'Upozornění: tímto se smažou všechny soubory ve složce, včetně podsložek. Tuto akci nelze vrátit zpět.',
                    ],
                ],
            ],
        ],
    ],
    'download_action' => [
        'label' => 'Stáhnout',
    ],
    'preview_action' => [
        'label' => 'Náhled',
        'modal_cancel_action_label' => 'Zavřít',
        'extra_modal_footer_actions' => [
            'preview' => [
                'label' => 'Otevřít v nové kartě',
            ],
        ],
    ],
    'view_action' => [
        'label' => 'Zobrazit',
        'modal_cancel_action_label' => 'Zavřít',
    ],
    'rename_action' => [
        'label' => 'Přejmenovat',
        'modal_heading' => 'Přejmenovat :displayName',
        'modal_submit_action_label' => 'Uložit název',
        'success_notification_title' => ':displayName úspěšně přejmenováno',
        'schema' => [
            'name' => [
                'label' => 'Název',
                'helper_text' => 'Aktuální název: :displayName',
            ],
        ],
    ],
    'replace_action' => [
        'label' => 'Nahradit',
        'modal_heading' => 'Nahradit :displayName',
        'modal_description' => 'Nahrajte nový soubor, který nahradí stávající soubor. Odkazy na soubor zůstanou stejné.',
        'modal_cancel_action_label' => 'Zrušit',
        'modal_submit_action_label' => 'Nahradit soubor',
        'success_notification_title' => ':displayName úspěšně nahrazeno',
        'schema' => [
            'file' => [
                'label' => 'Náhradní soubor',
                'below_content' => [
                    'text' => 'Můžete nahrát soubor až do velikosti :maxFileSizeFormatted.',
                ],
                'validation_messages' => [
                    'required' => 'Vyberte nebo přetáhněte soubor k nahrání.',
                ],
            ],
        ],
    ],
    'select_file_action' => [
        'label' => [
            'singular' => 'Vybrat soubor',
            'plural' => 'Vybrat soubory',
        ],
        'modal_heading' => [
            'singular' => 'Vybrat soubor',
            'plural' => 'Vybrat soubory',
        ],
        'modal_description' => [
            'min_files_one_max_files_null' => 'Vyberte alespoň jeden soubor',
            'min_files_one_max_files_non_null' => 'Vyberte 1 až :maxFiles souborů',
            'min_files_plural_max_files_null' => 'Vyberte alespoň :minFiles souborů',
            'min_files_one_max_files_one' => null,
            'min_files_equals_max_files' => 'Vyberte přesně :minFilesMaxFiles souborů',
        ],
        'modal_submit_action_label' => 'Vybrat',
        'modal_cancel_action_label' => 'Zrušit',
        'success_notification_title' => '{1}1 soubor vybrán|[2,*]:count souborů vybráno',
        'validation' => [
            'min_files' => '{1} Musíte vybrat alespoň :minFiles soubor|[2,*] Musíte vybrat alespoň :minFiles souborů',
            'max_files' => '{1} Nemůžete vybrat více než :maxFiles soubor|[2,*] Nemůžete vybrat více než :maxFiles souborů',
        ],
    ],
    'duplicate_action' => [
        'label' => 'Duplikovat',
        'modal_heading' => 'Duplikovat :displayName',
        'modal_submit_action_label' => 'Duplikovat',
        'success_notification_title' => ':displayName úspěšně duplikováno',
        'schema' => [
            'name' => [
                'label' => 'Nový název souboru',
                'helper_text' => 'Původní soubor: :displayName',
            ],
        ],
    ],
    'duplicate_bulk_action' => [
        'label' => 'Duplikovat',
        'success_notification_title' => '{0} Žádné soubory neduplikovány|{1} 1 soubor úspěšně duplikován|[2,*] :count souborů úspěšně duplikováno',
    ],
];
