<?php

return [
    'actions' => [
        'upload' => [
            'label' => 'Upload',
            'modal_heading' => 'Upload files',
            'modal_description' => 'Drag and drop or click to upload any new items to the library.',
            'modal_cancel_action_label' => 'Close',
        ],
    ],
    'exploreForm' => [
        'components' => [
            'filters' => [
                'schema' => [
                    'search' => [
                        'label' => 'Search',
                        'placeholder' => 'Search files or folder by name',
                    ],
                    'tabs' => [
                        'label' => 'Filters',
                    ],
                    'trigger' => [
                        'filters' => [
                            'label' => 'Filter',
                        ],
                    ],
                    'sorters' => [
                        'label' => 'Sort ',
                    ],
                    'display_options' => [
                        'label' => 'View',
                        'actions' => [
                            'update_file_version_grid' => [
                                'label' => 'Symbols',
                            ],
                            'update_file_version_row' => [
                                'label' => 'List',
                            ],
                            'toggle_file_is_file_extension_visible' => [
                                'label' => [
                                    'true' => 'Hide extensions',
                                    'false' => 'Show all file extensions',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'files' => [
                'empty_state' => [
                    'heading' => [
                        'not_found' => 'No files found',
                        'not_found_with_modifications' => 'Nothing matched your search',
                        'unauthorized' => 'You are not authorized to view this folder',
                    ],
                    'description' => [
                        'not_found' => 'Get started by uploading your first item.',
                        'not_found_with_modifications' => 'Try changing your filters or search for a different term.',
                        'unauthorized' => 'Please contact your administrator if you think this is a mistake.',
                    ],
                ],
            ],
            'selected_file' => [
                'empty_state_heading' => 'No file selected yet',
                'schema' => [
                    'information' => [
                        'heading' => 'Information',
                        'schema' => [
                            'uploader' => [
                                'label' => 'Uploaded by',
                            ],
                            'created_at' => [
                                'label' => 'Uploaded at',
                                'placeholder' => '-',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
