<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This addon facilitates client management of PayHost stored tokens
 *
 */

use WHMCS\Module\Addon\payhostpaybatch\Admin\AdminDispatcher;
use WHMCS\Module\Addon\payhostpaybatch\Client\ClientDispatcher;

if ( !defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

/**
 * Define addon module configuration parameters.
 *
 * @return array
 */
function payhostpaybatch_config()
{
    return [
        // Display name for your module
        'name'        => 'PayHost PayBatch Module',
        // Description displayed within the admin interface
        'description' => 'This module provides token management for the PayHost PayBatch Gateway ',
        // Module author name
        'author'      => 'AppInlet.com',
        // Default language
        'language'    => 'english',
        // Version number
        'version'     => '1.0',
        'fields'      => [
            // a text field type allows for single line text input
            'Text Field Name'     => [
                'FriendlyName' => 'Text Field Name',
                'Type'         => 'text',
                'Size'         => '25',
                'Default'      => 'Default value',
                'Description'  => 'Description goes here',
            ],
            // a password field type allows for masked text input
            'Password Field Name' => [
                'FriendlyName' => 'Password Field Name',
                'Type'         => 'password',
                'Size'         => '25',
                'Default'      => '',
                'Description'  => 'Enter secret value here',
            ],
            // the yesno field type displays a single checkbox option
            'Checkbox Field Name' => [
                'FriendlyName' => 'Checkbox Field Name',
                'Type'         => 'yesno',
                'Description'  => 'Tick to enable',
            ],
            // the dropdown field type renders a select menu of options
            'Dropdown Field Name' => [
                'FriendlyName' => 'Dropdown Field Name',
                'Type'         => 'dropdown',
                'Options'      => [
                    'option1' => 'Display Value 1',
                    'option2' => 'Second Option',
                    'option3' => 'Another Option',
                ],
                'Default'      => 'option2',
                'Description'  => 'Choose one',
            ],
            // the radio field type displays a series of radio button options
            'Radio Field Name'    => [
                'FriendlyName' => 'Radio Field Name',
                'Type'         => 'radio',
                'Options'      => 'First Option,Second Option,Third Option',
                'Default'      => 'Third Option',
                'Description'  => 'Choose your option!',
            ],
            // the textarea field type allows for multi-line text input
            'Textarea Field Name' => [
                'FriendlyName' => 'Textarea Field Name',
                'Type'         => 'textarea',
                'Rows'         => '3',
                'Cols'         => '60',
                'Default'      => 'A default value goes here...',
                'Description'  => 'Freeform multi-line text input field',
            ],
        ],
    ];
}

/**
 * Activate.
 *
 * Called upon activation of the module for the first time.
 * Use this function to perform any database and schema modifications
 * required by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function payhostpaybatch_activate()
{
    // Create custom tables and schema required by your module
}

/**
 * Deactivate.
 *
 * Called upon deactivation of the module.
 * Use this function to undo any database and schema modifications
 * performed by your module.
 *
 * This function is optional.
 *
 * @see https://developers.whmcs.com/advanced/db-interaction/
 *
 * @return array Optional success/failure message
 */
function payhostpaybatch_deactivate()
{
    // Undo any database and schema modifications made by your module here
}

/**
 * Upgrade.
 *
 * Called the first time the module is accessed following an update.
 * Use this function to perform any required database and schema modifications.
 *
 * This function is optional.
 *
 * @see https://laravel.com/docs/5.2/migrations
 *
 * @return void
 */
function payhostpaybatch_upgrade( $vars )
{
}

/**
 * Admin Area Output.
 *
 * Called when the addon module is accessed via the admin area.
 * Should return HTML output for display to the admin user.
 *
 * This function is optional.
 *
 * @return string
 * @see payhostpaybatch\Admin\Controller::index()
 *
 */
function payhostpaybatch_output( $vars )
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. payhostpaybatchs.php?module=payhostpaybatch
    $version    = $vars['version']; // eg. 1.0
    $_lang      = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField     = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField    = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    // Dispatch and handle request here. What follows is a demonstration of one
    // possible way of handling this using a very basic dispatcher implementation.

    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

    $dispatcher = new AdminDispatcher();
    $response   = $dispatcher->dispatch( $action, $vars );
    echo $response;
}

/**
 * Admin Area Sidebar Output.
 *
 * Used to render output in the admin area sidebar.
 * This function is optional.
 *
 * @param array $vars
 *
 * @return string
 */
function payhostpaybatch_sidebar( $vars )
{
    // Get common module parameters
    $modulelink = $vars['modulelink'];
    $version    = $vars['version'];
    $_lang      = $vars['_lang'];

    // Get module configuration parameters
    $configTextField     = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField    = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    $sidebar = '<p>Sidebar output HTML goes here</p>';
    return $sidebar;
}

/**
 * Client Area Output.
 *
 * Called when the addon module is accessed via the client area.
 * Should return an array of output parameters.
 *
 * This function is optional.
 *
 * @return array
 * @see payhostpaybatch\Client\Controller::index()
 *
 */
function payhostpaybatch_clientarea( $vars )
{
    // Get common module parameters
    $modulelink = $vars['modulelink']; // eg. index.php?m=payhostpaybatch
    $version    = $vars['version']; // eg. 1.0
    $_lang      = $vars['_lang']; // an array of the currently loaded language variables

    // Get module configuration parameters
    $configTextField     = $vars['Text Field Name'];
    $configPasswordField = $vars['Password Field Name'];
    $configCheckboxField = $vars['Checkbox Field Name'];
    $configDropdownField = $vars['Dropdown Field Name'];
    $configRadioField    = $vars['Radio Field Name'];
    $configTextareaField = $vars['Textarea Field Name'];

    /**
     * Dispatch and handle request here. What follows is a demonstration of one
     * possible way of handling this using a very basic dispatcher implementation.
     */

    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

    $dispatcher = new ClientDispatcher();
    return $dispatcher->dispatch( $action, $vars );
}
