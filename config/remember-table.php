<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    |
    | The table that stores saved views, and the model used to read it.
    | You may extend the model and point this setting to your own class.
    |
    */

    'table_name' => 'remember_table_views',

    'model' => \Sehsah\FilamentRememberTable\Models\TableView::class,

    /*
    |--------------------------------------------------------------------------
    | What a view remembers
    |--------------------------------------------------------------------------
    |
    | Turn off any part of the table state you don't want stored in views.
    |
    */

    'remember' => [
        'filters' => true,
        'search' => true,
        'column_searches' => true,
        'sort' => true,
        'grouping' => true,
        'columns' => true,
        'per_page' => false,
        'tab' => true, // resource list page tabs (getTabs())
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    // Let users share a view with every user of the panel.
    'allow_public_views' => true,

    // Apply the user's default view when they open the table without
    // any table state in the URL.
    'apply_default_view_on_load' => true,

    // Scope views to the current Filament tenant when tenancy is enabled.
    'scope_to_tenant' => true,

    // Maximum number of views one user can save per table (null = no limit).
    'max_views_per_table' => 50,

];
