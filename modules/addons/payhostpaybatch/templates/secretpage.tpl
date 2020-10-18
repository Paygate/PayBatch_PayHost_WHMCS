{* Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *}
<h2>PayHost Token Management Page</h2>

<div class="row">
    <h3>Stored user tokens</h3>
    <table>
        <thead>
        <tr><th class="col-sm-2">User</th><th class="col-sm-3">Token</th><th class="col-sm-2">Card Number</th><th class="col-sm-2">Expiry</th><th class="col-sm-2">Action</th></tr>
        </thead>
        <tbody>
        {foreach $userTokens as $userToken}
            <tr>
                <td class="col-sm-2">{$userId}</td>
                <td class="col-sm-3">{$userToken['token']}</td>
                <td class="col-sm-2">{$userToken['cardNumber']}</td>
                <td class="col-sm-2">{$userToken['expDate']}</td>
                <td>
                    <a href="{$modulelink}&action=deleteToken&tokenId={$userToken['token']}">Delete</a>
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>

</div>

<hr>

<p>



</p>
