<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace WHMCS\Module\Addon\PayhostPaybatch\Client;

/**
 * Client Area Dispatch Handler
 */
class ClientDispatcher
{

    /**
     * Dispatch request.
     *
     * @param string $action
     * @param array $parameters
     *
     * @return array
     */
    public function dispatch($action, $parameters)
    {
        if ( ! $action) {
            // Default to secret if no action specified
            $action = 'secret';
        }

        $controller = new Controller();

        // Verify requested action is valid and callable
        if (is_callable(array($controller, $action))) {
            return $controller->$action($parameters);
        }
    }
}
