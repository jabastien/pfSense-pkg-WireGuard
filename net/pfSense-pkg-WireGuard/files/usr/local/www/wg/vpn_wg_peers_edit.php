<?php
/*
 * vpn_wg_peers_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
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
##|*IDENT=page-vpn-wireguard
##|*NAME=VPN: WireGuard: Edit
##|*DESCR=Allow access to the 'VPN: WireGuard' page.
##|*MATCH=vpn_wg_peers_edit.php*
##|-PRIV

// pfSense includes
require_once('guiconfig.inc');

// WireGuard includes
require_once('wireguard/includes/wg.inc');
require_once('wireguard/includes/wg_guiconfig.inc');

global $wgg;

$pconfig = array();

wg_globals();

// This is the main entry into the post switchboard for this page.
['input_errors' => $input_errors, 'is_apply' => $is_apply, 'pconfig' => $pconfig, 'ret_code' => $ret_code] = wg_peers_edit_post_handler($_POST);

// Are we editing an existing peer?
if (!([$peer_idx, $pconfig, $is_new] = wg_peer_get_config($_REQUEST['peer'], false))) {

	// New peer defaults
	$is_new 		= true;

	// Default to enabled
	$pconfig['enabled']	= 'yes';

	// Automatically choose a tunnel based on the request 
	$pconfig['tun'] 	= isset($_REQUEST['tun']) ? $_REQUEST['tun'] : null;

	// Default to a dynamic tunnel, so hide the endpoint form group
	$is_dynamic 		= true;

}

$s = fn($x) => $x;

$shortcut_section = 'wireguard';

$pgtitle = array(gettext('VPN'), gettext('WireGuard'), gettext('Peers'), gettext('Edit'));
$pglinks = array('', '/wg/vpn_wg_tunnels.php', '/wg/vpn_wg_peers.php', '@self');

$tab_array = array();
$tab_array[] = array(gettext('Tunnels'), false, '/wg/vpn_wg_tunnels.php');
$tab_array[] = array(gettext('Peers'), true, '/wg/vpn_wg_peers.php');
$tab_array[] = array(gettext('Settings'), false, '/wg/vpn_wg_settings.php');

include('head.inc');

wg_print_service_warning();

if (isset($_POST['apply'])) {

	print_apply_result_box($ret_code);

}

wg_print_config_apply_box();

if (!empty($input_errors)) {

	print_input_errors($input_errors);

}

display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section(gettext('Peer Configuration'));

$form->addGlobal(new Form_Input(
	'index',
	'',
	'hidden',
	$peer_idx
));

$section->addInput(new Form_Checkbox(
	'enabled',
	'Enable',
	gettext('Enable Peer'),
	$pconfig['enabled'] == 'yes'
))->setHelp('<span class="text-danger">Note: </span>Uncheck this option to disable this peer without removing it from the list.');

$section->addInput($input = new Form_Select(
	'tun',
	gettext('Tunnel'),
	$pconfig['tun'],
	wg_get_tun_list()
))->setHelp("WireGuard tunnel for this peer. (<a href='vpn_wg_tunnels_edit.php'>Create a New Tunnel</a>)");

$section->addInput(new Form_Input(
	'descr',
	gettext('Description'),
	'text',
	$pconfig['descr'],
	['placeholder' => 'Description']
))->setHelp("Peer description for administrative reference (not parsed).");

$section->addInput(new Form_Checkbox(
	'dynamic',
	gettext('Dynamic Endpoint'),
	gettext('Dynamic'),
	empty($pconfig['endpoint']) || $is_dynamic
))->setHelp('<span class="text-danger">Note: </span>Uncheck this option to assign an endpoint address and port for this peer.');

$group = new Form_Group('Endpoint');

// Used for hiding/showing the group via JS
$group->addClass("endpoint");

$group->add(new Form_Input(
	'endpoint',
	gettext('Endpoint'),
	'text',
	$pconfig['endpoint']
))->addClass('trim')
  ->setHelp('Hostname, IPv4, or IPv6 address of this peer.<br />
	     Leave endpoint and port blank if unknown (dynamic endpoints).')
  ->setWidth(5);

$group->add(new Form_Input(
	'port',
	gettext('Endpoint Port'),
	'text',
	$pconfig['port']
))->addClass('trim')
  ->setHelp("Port used by this peer.<br />
	     Leave blank for default ({$wgg['default_port']}).")
  ->setWidth(3);

$section->add($group);

$section->addInput(new Form_Input(
	'persistentkeepalive',
	gettext('Keep Alive'),
	'text',
	$pconfig['persistentkeepalive'],
	['placeholder' => 'Keep Alive']
))->addClass('trim')
  ->setHelp('Interval (in seconds) for Keep Alive packets sent to this peer.<br />
	     Default is empty (disabled).');

$section->addInput(new Form_Input(
	'publickey',
	'*Public Key',
	'text',
	$pconfig['publickey'],
	['placeholder' => 'Public Key']
))->addClass('trim')
  ->setHelp('WireGuard public key for this peer.');

$group = new Form_Group('Pre-shared Key');

$group->add(new Form_Input(
	'presharedkey',
	gettext('Pre-shared Key'),
	wg_secret_input_type(),
	$pconfig['presharedkey']
))->addClass('trim')
  ->setHelp('Optional pre-shared key for this tunnel. (<a id="copypsk" style="cursor: pointer;" data-success-text="Copied" data-timeout="3000">Copy</a>)');

$group->add(new Form_Button(
	'genpsk',
	gettext('Generate'),
	null,
	'fa-key'
))->addClass('btn-primary btn-sm')
  ->setHelp('New Pre-shared Key');

$section->add($group);

$form->add($section);

$section = new Form_Section('Address Configuration');

// Init the addresses array if necessary
if (!is_array($pconfig['allowedips']['row']) || empty($pconfig['allowedips']['row'])) {

	wg_init_config_arr($pconfig, array('allowedips', 'row', 0));
	
	// Hack to ensure empty lists default to /128 mask
	$pconfig['allowedips']['row'][0]['mask'] = '128';
	
}

$last = count($pconfig['allowedips']['row']) - 1;

foreach ($pconfig['allowedips']['row'] as $counter => $item) {

	$group = new Form_Group($counter == 0 ? 'Allowed IPs' : null);

	$group->addClass('repeatable');

	$group->add(new Form_IpAddress(
		"address{$counter}",
		gettext('Allowed Subnet or Host'),
		$item['address'],
		'BOTH'
	))->addClass('trim')
	  ->setHelp($counter == $last ? 'IPv4 or IPv6 subnet or host reachable via this peer.' : '')
	  ->addMask("address_subnet{$counter}", $item['mask'], 128, 0)
	  ->setWidth(4);

	$group->add(new Form_Input(
		"address_descr{$counter}",
		gettext('Description'),
		'text',
		$item['descr']
	))->setHelp($counter == $last ? 'Description for administrative reference (not parsed).' : '')
	  ->setWidth(4);

	$group->add(new Form_Button(
		"deleterow{$counter}",
		gettext('Delete'),
		null,
		'fa-trash'
	))->addClass('btn-warning btn-sm');

	$section->add($group);

}

$section->addInput(new Form_Button(
	'addrow',
	gettext('Add Allowed IP'),
	null,
	'fa-plus'
))->addClass('btn-success btn-sm addbtn');

$form->add($section);

$form->addGlobal(new Form_Input(
	'act',
	'',
	'hidden',
	'save'
));

print($form);

?>

<nav class="action-buttons">
<?php
// We cheat here and show disabled buttons for a better user experience
if ($is_new):
?>
	<button class="btn btn-danger btn-sm" title="<?=gettext('Delete Peer')?>" disabled>
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext('Delete Peer')?>
	</button>
<?php
else:
?>
	<a id="peerdelete" class="btn btn-danger btn-sm no-confirm">
		<i class="fa fa-trash icon-embed-btn"></i>
		<?=gettext('Delete Peer')?>
	</a>
<?php
endif;
?>
	<button type="submit" id="saveform" name="saveform" class="btn btn-primary btn-sm" value="save" title="<?=gettext('Save Peer')?>">
		<i class="fa fa-save icon-embed-btn"></i>
		<?=gettext("Save Peer")?>
	</button>
</nav>

<?php 

wg_print_status_hint();

$genKeyWarning = gettext("Overwrite pre-shared key? Click 'ok' to overwrite key.");

$deletePeerWarning = gettext("Delete Peer? Click 'ok' to delete peer.");

?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

	// Supress "Delete" button if there are fewer than two rows
	checkLastRow();

	wgRegTrimHandler();

	$('#copypsk').click(function(event) {

		var $this = $(this);

		var originalText = $this.text();

		// The 'modern' way...
		navigator.clipboard.writeText($('#presharedkey').val());

		$this.text($this.attr('data-success-text'));

		setTimeout(function() {

			$this.text(originalText);

		}, $this.attr('data-timeout'));

		// Prevents the browser from scrolling
		return false;

	});

	// These are action buttons, not submit buttons
	$('#genpsk').prop('type','button');

	// Request a new pre-shared key
	$('#genpsk').click(function(event) {
		if ($('#presharedkey').val().length == 0 || confirm(<?=json_encode($genKeyWarning)?>)) {
			ajaxRequest = $.ajax({
				url: "/wg/vpn_wg_peers_edit.php",
				type: "post",
				data: {
					act: "genpsk"
				},
				success: function(response, textStatus, jqXHR) {
					$('#presharedkey').val(response);
				}
			});
		}
	});

	$('#peerdelete').click(function(event) {
		if (confirm(<?=json_encode($deletePeerWarning)?>)) {
			postSubmit({act: 'delete', peer: <?=json_encode($peer_idx)?>}, '/wg/vpn_wg_peers.php');
		}
	});

	// Save the form
	$('#saveform').click(function(event) {

		$(form).submit();

	});

	$('#dynamic').click(function(event) {

		updateDynamicSection(this.checked);

	});

	function updateDynamicSection(hide) {

		hideClass('endpoint', hide);

	}

	updateDynamicSection($('#dynamic').prop('checked'));

});
//]]>
</script>

<?php
include('wireguard/includes/wg_foot.inc');
include('foot.inc');
?>