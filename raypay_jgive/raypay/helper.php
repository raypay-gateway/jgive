<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_jgive
 * @subpackage 	RayPay JGive
 * @copyright   RayPay => https://raypay.ir
 * @copyright   Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
 
defined( '_JEXEC' ) or die( ';)' );
	jimport('joomla.html.html');
	jimport( 'joomla.plugin.helper' );
class plgPaymentRayPayHelper
{ 		
	function Storelog($name,$logdata)
	{
		jimport('joomla.error.log');
		$options = "{DATE}\t{TIME}\t{USER}\t{DESC}";

		$my = JFactory::getUser();

		JLog::addLogger(
			array(
				'text_file' => $logdata['JT_CLIENT'].'_'.$name.'.log',
				'text_entry_format' => $options
			),
			JLog::INFO,
			$logdata['JT_CLIENT']
		);

		$logEntry = new JLogEntry('Transaction added', JLog::INFO, $logdata['JT_CLIENT']);
		$logEntry->user = $my->name.'('.$my->id.')';
		$logEntry->desc = json_encode($logdata['raw_data']);

		JLog::add($logEntry);
	}

	function saveComment($pg_plugin, $oid, $comment)
		{
			if ($oid)
			{
				$obj   = new stdClass;
				$db    = JFactory::getDBO();
				$query = "SELECT donation_id FROM #__jg_orders WHERE id =" . $oid;
				$db->setQuery($query);

				$obj->id      = $db->loadResult();
				$obj->comment = $comment;

				if ($obj->id)
				{
					if (!$db->updateObject('#__jg_donations', $obj, 'id'))
					{
						echo $db->stderr();
					}
				}
			}
		}
}
