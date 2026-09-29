<?php

return [

    'button' => [
        'label' => 'Views',
        'modified' => ':name (modified)',
    ],

    'actions' => [
        'save' => 'Save as new view',
        'save_submit' => 'Save view',
        'update' => 'Update current view',
        'manage' => 'Manage views',
        'manage_submit' => 'Save changes',
        'reset' => 'Reset table',
    ],

    'modals' => [
        'save' => [
            'heading' => 'Save view',
            'description' => 'Saves the current filters, search, sorting, grouping and columns.',
        ],
        'update' => [
            'heading' => 'Update ":name"?',
            'description' => 'The view will be replaced with the current table settings.',
        ],
        'manage' => [
            'heading' => 'Manage views',
        ],
    ],

    'fields' => [
        'name' => 'Name',
        'is_default' => 'Default view',
        'is_public' => 'Share with everyone',
        'is_public_help' => 'Other users can apply this view, but only you can change it.',
    ],

    'badges' => [
        'default' => 'Default',
        'shared' => 'Shared',
        'preset' => 'Preset',
    ],

    'notifications' => [
        'saved' => 'View ":name" saved',
        'updated' => 'View ":name" updated',
        'applied' => 'Showing ":name"',
        'managed' => 'Views updated',
        'limit_reached' => 'You can save up to :limit views for this table.',
    ],

];
