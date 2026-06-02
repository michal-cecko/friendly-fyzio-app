<?php

return [
    'upload_action' => [
        'label' => 'Upload',
        'modal_heading' => 'Upload files',
        'modal_description' => 'Drag and drop or click to upload any new items to the library.',
        'modal_cancel_action_label' => 'Close',
        'modal_submit_action_label' => 'Save files',
        'success_notification_title' => '{1}:count file uploaded successfully|[2,*]:count files uploaded successfully',
        'schema' => [
            'files' => [
                'label' => 'Upload file',
                'below_content' => [
                    'text' => 'You can upload files up to :maxFileSizeFormatted.',
                ],
                'validation_messages' => [
                    'required' => 'Please select or drop a file to upload.',
                ],
            ],
        ],
    ],
    'create_folder_action' => [
        'label' => 'Create folder',
        'modal_submit_action_label' => 'Save folder',
        'success_notification_title' => 'Folder :folderName created successfully',
        'schema' => [
            'name' => [
                'label' => 'Folder name',
                'below_content' => [
                    'text' => [
                        'allows_directory_separator_in_folder_name' => [
                            'true' => 'Navigate into the folder to create sub-directories.',
                            'false' => 'You can use `:directorySeparator` to create sub-directories.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'delete_bulk_action' => [
        'label' => 'Delete',
        'modal_heading' => 'Delete 1 item|Delete :count items',
        'modal_description' => [
            'with_folders' => '{1} The content for the selected folder will be removed permanently. Are you sure you would like to do this?|[2,*] The content for the selected :count folders will be removed permanently. Are you sure you would like to do this?',
            'without_folders' => 'Are you sure you would like to permanently delete these items?',
        ],
        'modal_submit_action_label' => '{0} Delete 0 items|{1} Delete 1 item|[2,*] Delete :count items',
        'success_notification_title' => '{0} No items deleted|{1} 1 item deleted successfully|[2,*] :count items deleted successfully',
    ],
    'move_bulk_action' => [
        'label' => 'Move',
        'modal_heading' => 'Move 1 item|Move :count items',
        'modal_submit_action_label' => [
            'root' => '{0} Move 0 items|{1} Move 1 item|[2,*] Move :count items',
            'folder' => '{0} Move 0 items to :displayName|{1} Move 1 item to :displayName|[2,*] Move :count items to :displayName',
        ],
        'success_notification_title' => '{0} No items moved|{1} 1 item moved successfully|[2,*] :count items moved successfully',
    ],
    'move_action' => [
        'label' => 'Move',
        'modal_heading' => 'Move :displayName',
        'modal_submit_action_label' => [
            'root' => 'Move to root',
            'folder' => 'Move to :displayName',
        ],
        'success_notification_title' => ':displayName moved successfully',
        'failure_notification_title' => [
            'existing_target_folder_file' => 'A file named :basename already exists in :targetFolder',
            'root' => 'the root folder',
        ],
    ],
    'create_folder_bulk_action_bulk_action' => [
        'label' => 'New folder from selection',
        'modal_heading' => 'New folder from selection',
        'modal_submit_action_label' => '{0} Create folder and move 0 items|{1} Create folder and move 1 item|[2,*] Create folder and move :count items',
        'success_notification_title' => 'Folder :folderName created successfully with :count items moved into it',
        'schema' => [
            'name' => [
                'label' => 'Folder name',
            ],
        ],
    ],
    'delete_action' => [
        'label' => 'Delete',
        'modal_heading' => [
            'file' => 'Delete file :displayName',
            'folder' => 'Delete folder :displayName',
        ],
        'modal_description' => [
            'file' => 'Are you sure you would like to delete this file?',
            'folder' => 'Any files in the folder will not be deleted, but moved to the current folder. Are you sure you would like to delete this folder?',
        ],
        'success_notification_title' => [
            'file' => 'File :displayName deleted successfully',
            'folder' => [
                'delete_content' => 'Folder :displayName deleted successfully, including all content',
                'preserve_content' => 'Folder :displayName deleted successfully',
            ],
        ],
        'schema' => [
            'delete_content' => [
                'label' => 'Delete all content in folder',
                'below_content' => [
                    'text' => [
                        'true' => 'Warning: this will delete all files in the folder, including sub-folders. This cannot be undone.',
                    ],
                ],
            ],
        ],
    ],
    'download_action' => [
        'label' => 'Download',
    ],
    'preview_action' => [
        'label' => 'Preview',
        'modal_cancel_action_label' => 'Close',
        'extra_modal_footer_actions' => [
            'preview' => [
                'label' => 'Open in new tab',
            ],
        ],
    ],
    'view_action' => [
        'label' => 'View',
        'modal_cancel_action_label' => 'Close',
    ],
    'rename_action' => [
        'label' => 'Rename',
        'modal_heading' => 'Rename :displayName',
        'modal_submit_action_label' => 'Save name',
        'success_notification_title' => ':displayName renamed successfully',
        'schema' => [
            'name' => [
                'label' => 'Name',
                'helper_text' => 'Current name: :displayName',
            ],
        ],
    ],
    'replace_action' => [
        'label' => 'Replace',
        'modal_heading' => 'Replace :displayName',
        'modal_description' => 'Upload a new file to replace the existing file. References to the file will remain the same.',
        'modal_cancel_action_label' => 'Cancel',
        'modal_submit_action_label' => 'Replace file',
        'success_notification_title' => ':displayName replaced successfully',
        'schema' => [
            'file' => [
                'label' => 'Replacement file',
                'below_content' => [
                    'text' => 'You can upload a file up to :maxFileSizeFormatted.',
                ],
                'validation_messages' => [
                    'required' => 'Please select or drop a file to upload.',
                ],
            ],
        ],
    ],
    'select_file_action' => [
        'label' => [
            'singular' => 'Select file',
            'plural' => 'Select files',
        ],
        'modal_heading' => [
            'singular' => 'Select file',
            'plural' => 'Select files',
        ],
        'modal_description' => [
            'min_files_one_max_files_null' => 'Select at least one file',
            'min_files_one_max_files_non_null' => 'Select between 1 and :maxFiles files',
            'min_files_plural_max_files_null' => 'Select at least :minFiles files',
            'min_files_one_max_files_one' => null,
            'min_files_equals_max_files' => 'Select exactly :minFilesMaxFiles files',
        ],
        'modal_submit_action_label' => 'Select',
        'modal_cancel_action_label' => 'Cancel',
        'success_notification_title' => '{1}1 file selected|[2,*]:count files selected',
        'validation' => [
            'min_files' => '{1} You must select at least :minFiles file|[2,*] You must select at least :minFiles files',
            'max_files' => '{1} You cannot select more than :maxFiles file|[2,*] You cannot select more than :maxFiles files',
        ],
    ],
    'duplicate_action' => [
        'label' => 'Duplicate',
        'modal_heading' => 'Duplicate :displayName',
        'modal_submit_action_label' => 'Duplicate',
        'success_notification_title' => ':displayName duplicated successfully',
        'schema' => [
            'name' => [
                'label' => 'New filename',
                'helper_text' => 'Original file: :displayName',
            ],
        ],
    ],
    'duplicate_bulk_action' => [
        'label' => 'Duplicate',
        'success_notification_title' => '{0} No files duplicated|{1} 1 file duplicated successfully|[2,*] :count files duplicated successfully',
    ],
];
