<?php
/*
 * status_wireguard_routes.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
 * Copyright (c) 2021 Vajonam
 * Copyright (c) 2020 Ascrod
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-status-wireguard
##|*NAME=Status: WireGuard
##|*DESCR=Allow access to the 'Status: WireGuard' page.
##|*MATCH=status_wireguard.php*
##|-PRIV

// pfSense includes
require_once('guiconfig.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

global $wgg;

wg_globals();

if ($_POST) {

	if (isset($_POST['apply'])) {

		$ret_code = 0;

		if (is_subsystem_dirty($wgg['subsystems']['wg'])) {

			if (wg_is_service_running()) {

				$tunnels_to_apply = wg_apply_list_get('tunnels');

				$sync_status = wg_tunnel_sync($tunnels_to_apply, true, true);

				$ret_code |= $sync_status['ret_code'];

			}

			if ($ret_code == 0) {

				clear_subsystem_dirty($wgg['subsystems']['wg']);

			}

		}

	}

}

$s = fn($x) => $x;

$shortcut_section = "wireguard";

$pgtitle = array(gettext("Status"), gettext("WireGuard"));
$pglinks = array("", "@self");

$tab_array = array();
$tab_array[] = array(gettext("WireGuard"), false, '/wg/status_wireguard.php');
$tab_array[] = array(gettext("Routes"), true, '/wg/status_wireguard_routes.php');
$tab_array[] = array(gettext("Package"), false, '/wg/status_wireguard_package.php');
$tab_array[] = array("[{$s(gettext('Configuration'))}]", false, '/wg/vpn_wg_tunnels.php');

include("head.inc");

wg_print_service_warning();

if (isset($_POST['apply'])) {

	print_apply_result_box($ret_code);

}

wg_print_config_apply_box();

display_top_tabs($tab_array);

?>

<?php
include('wireguard/includes/wg_foot.inc');
include('foot.inc');
?>